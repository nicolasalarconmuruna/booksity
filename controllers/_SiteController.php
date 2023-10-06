<?php

namespace app\controllers;

use Transbank\Webpay\Configuration;
use Transbank\Webpay\Webpay;
use Yii;
use yii\db\Exception;
use yii\rest\Controller;
use yii\web\HttpException;
use yii\web\Response;
use app\components\Instalaciones;
use app\components\Reservas;
class SiteController extends Controller
{
    public $enableCsrfValidation = false;
    public function init()
    {
        parent::init();
        \Yii::$app->user->enableSession = false;
    }
    /**
     * behaviors
     * @return array
     */
    public static function allowedDomains() {
        return [
            '*',  // star allows all domains
        ];
    }

    /**
     * behaviors
     * @return array
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['contentNegotiator'] = [
            'class' => 'yii\filters\ContentNegotiator',
            'formats' =>
                ['application/json' => Response::FORMAT_JSON],
        ];

        // For cross-domain AJAX request
        $behaviors['corsFilter']  = [
            'class' => \yii\filters\Cors::className(),
            'cors'  => [
                // restrict access to domains:
                'Origin'  => static::allowedDomains(),
                'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Origin' => ['*'],
                'Access-Control-Max-Age'      => 1000,   // Cache (seconds)
            ],
        ];
        $behaviors['bearerAuth'] = [
            'class' => \yii\filters\auth\HttpBearerAuth::className(),
        ];
        return $behaviors;
    }

    /**
     * Send email for remaining payment
     * @return array
     * @throws HttpException
     */
    public function actionSendPaymentEmail()
    {
        $this->validateRequiredParams(['reservas_id']);
        $Reservas = \app\models\Reservas::findOne(Yii::$app->request->post('reservas_id'));
        $total_paid_amount = \app\models\ReservasPagos::find()
            ->andWhere(['reservas_id'=>Yii::$app->request->post('reservas_id')])
            ->sum('valor');
        if(!$Reservas){
            return ['status'=>0,'message'=>'Bad Request'];
        }
        $Reservas->payment_link_created_at = date('Y-m-d H:i:s');
        $Reservas->save(false);
        $Clientes = \app\models\Clientes::findOne($Reservas->clientes_id_billing);
        $user_name = $Clientes->nombres;
        $user_email = $Clientes->email;
        $remain_amount = $Reservas->valor_a_pagar - $total_paid_amount;
        $token = Yii::$app->security->maskToken(Yii::$app->request->post('reservas_id'));
        $email_content = $this->render('//payment/email_payment_reminder',['token'=>$token,'user_name'=>$user_name]);
        Yii::$app->mailer->compose()
        ->setHtmlBody($email_content)
        ->setFrom(['info@bahiaisidoro.cl' => 'Bahía Isidoro'])
        ->setTo($user_email)
        ->setSubject('Bookisity - Remaining Payment Reminder')
        ->send();
        return ['status'=>1,'message'=>'Email sent successfully.'];
    }
    /**
     * Cancel Payment API
     * @return array
     * @throws HttpException
     */
    public function actionNullifypayment()
    {

        $ReservasPagos = \app\models\ReservasPagos::find()
            ->andWhere(['created_by'=>2,'formas_pago_id'=>2,'id'=>Yii::$app->request->post('reservas_id')])->one();
        if(!$ReservasPagos){
            return ['status'=>0,'message'=>'No payment found.'];
        }
        $configuration = Configuration::forTestingWebpayPlusNormal();
        $webpay = new Webpay($configuration);
        $commercecode = null;
        $authorizationCode = $ReservasPagos->authorization_token;
        $authorizedAmount = $ReservasPagos->authorized_amount;
        $buyOrder = $ReservasPagos->voucher;
        $nullifyAmount = $ReservasPagos->valor;
        $result = $webpay->getNullifyTransaction()->nullify($authorizationCode, $authorizedAmount, $buyOrder, $nullifyAmount,$commercecode);

        if (isset($result->authorizationCode)) {
            $ReservasPagos->valor = 0;
            $ReservasPagos->nullify_token = $result->authorizationCode;
            $ReservasPagos->save(false);
            $total_paid_amount = \app\models\ReservasPagos::find()
                ->andWhere(['reservas_id'=>$ReservasPagos->reservas_id])
                ->sum('valor');
            if($total_paid_amount <= 0){
                $Reservas = \app\models\Reservas::findOne($ReservasPagos->reservas_id);
                $Reservas->status = 1;
                $Reservas->save(false);
            }
            return ['status'=>1,'message'=>'Transacci&oacute;n anulada con exito en Webpay'];
        } else {
            return ['status'=>0,'message'=>'webpay no disponible'];
        }
    }

    /**
     * Get season price list API
     * @return array
     * @throws Exception
     */
    public function actionSeasonprices()
    {
        $query_header="SELECT IT.nombre, valor_rack as cnt
				FROM instalaciones_tipo as IT 
				ORDER by IT.id";
        $data['installation_types'] = \Yii::$app->db->createCommand($query_header)->queryAll();

        $query_temporadas="
					SELECT T.Tnombre, TR.fecha_inicio, TR.fecha_fin, GROUP_CONCAT(DISTINCT T.ITnombre, ';', IFNULL(TA.valor, 0) ORDER BY T.ITid SEPARATOR '|') as valor 
					FROM 
						(SELECT DISTINCT
							T.id as Tid,
						 	T.nombre as Tnombre,
							IT.id as ITid,
						 	IT.nombre as ITnombre
						FROM temporadas T, instalaciones_tipo IT) as T
					LEFT JOIN tarifas TA ON (TA.temporadas_id = T.Tid AND TA.instalaciones_tipo_id = T.ITid)
					LEFT JOIN temporadas_rangos TR ON T.Tid = TR.temporadas_id
					WHERE TR.fecha_fin >= CURDATE()
					GROUP BY T.Tid
					ORDER by T.ITid
					";
        $data['pricing_data'] = \Yii::$app->db->createCommand($query_temporadas)->queryAll();
        return ['status'=>1,'mesage'=>'','data'=>$data];
    }
    /**
     * default action
     * @return array
     */
    public function actionIndex()
    {
        return ['status'=>400,'message'=>'Bad Request','data'=>[]];
    }
    /**
     * get available cabin list against installation type
     * @return array
     * @throws HttpException
     * @throws Exception
     */
    public function actionGetavailablecabin()
    {
        $this->validateRequiredParams(['installation_type_id']);
        $query = 'SELECT * FROM `instalaciones_tipo` WHERE `id` = :id ';
        $installation_type =\Yii::$app->db->createCommand($query)
            ->bindValue(':id' ,Yii::$app->request->post('installation_type_id') )
            ->queryOne();
        if(!$installation_type){
            return ['status'=>0,'message'=>'Invalid Type  Id'];
        }
        $query = 'SELECT count(*) as total FROM `instalaciones` WHERE `online` = 1 AND `tipo_instalacion_id` = :installation_type_id AND `status` = 1 ';
        $data =\Yii::$app->db->createCommand($query)
            ->bindValue(':installation_type_id' ,Yii::$app->request->post('installation_type_id') )
            ->queryOne();
        $options = '';
        for ($i=1;$i<=$data['total'];$i++){
            $options .= "<option value='".$i."' max-guest='".($i*$installation_type['max_guests'])."'>".$i."</option>";
        }
        return ['status'=>1,'message'=>'Success','data'=>$options];
    }

    /**
     * Get list of installation types
     * @return array
     * @throws Exception
     */
    public function actionGettypelist()
    {
        $query = 'SELECT * FROM `instalaciones_tipo` ';
        $data =\Yii::$app->db->createCommand($query)
            ->queryAll();
        $options = "<option>Seleccionar tipo</option>";
        foreach($data as $installation){
            $options .= "<option value='".$installation['id']."' max-guest='".$installation['max_guests']."'>".$installation['nombre']."</option>";
        }
        return ['status'=>1,'message'=>'Success','data'=>$options];
    }

    /**
     * Check room availability based on date and installation types
     * @return array
     * @throws HttpException
     */
    public function actionCheckAvailability()
    {
        $this->validateRequiredParams(['from', 'to','instalaciones','adultos','ninos','tipo']);
        if (Yii::$app->request->post('from') < date('Y-m-d')){
            return ['status'=>0,'message'=>'Por favor seleccione fecha futura.'];
        }
        if (Yii::$app->request->post('from') >= Yii::$app->request->post('to')){
            return ['status'=>0,'message'=> 'La fecha de Check In debe ser menor que la fecha de Check Out.'];
        }
        $rooms = Instalaciones::is_available(Yii::$app->request->post('from'), Yii::$app->request->post('to'),false,Yii::$app->request->post('tipo'),false,'online');
        if($rooms  && count($rooms) >= Yii::$app->request->post('instalaciones')){
            return ['status'=>1,'message'=>'tenemos habitaciones disponibles para ti'];
        }
        if(Yii::$app->request->post('instalaciones') == 1){
            $rooms = Instalaciones::is_combination_suggestion_available(Yii::$app->request->post('from'), Yii::$app->request->post('to'),false, Yii::$app->request->post('tipo'));
            if($rooms['status']){
                //echo "<pre>";print_r($rooms['data']);exit;
                $data = $this->renderAjax('/payment/package_suggestion',['data'=>$rooms['data']]);
                return ['status'=>2,'message'=>'No se encontró una instalación disponible por todo el periodo consultado. Sin embargo, combinando las siguientes opciones se puede cubrir todo el periodo. Para ello, el cliente deberá cambiar de instalación durante su estadía.','data'=>$data];
            }
        }
        return ['status'=>0,'message'=>'Lo sentimos, no se encontraron instalaciones disponibles para esa fecha.'];
    }
    /**
     * Book room in pending status based on email if user is not exists it will automatically
     * add user in system and book room against that user
     * @return array
     * @throws HttpException
     */
    public function actionBookRoom()
    {
        $success = false;
        $limit = 0;
            $this->validateRequiredParams(['from', 'to','instalaciones','adultos','ninos','pasaporte','nombres','email','telefono','tipo']);
        $rooms = Instalaciones::is_available(Yii::$app->request->post('from'), Yii::$app->request->post('to'),false,Yii::$app->request->post('tipo'),false,'online');
        if(!$rooms  || count($rooms) < Yii::$app->request->post('instalaciones')){
            if(Yii::$app->request->post('instalaciones') == 1){
                $rooms_suggestion = Instalaciones::is_combination_suggestion_available(Yii::$app->request->post('from'), Yii::$app->request->post('to'),false, Yii::$app->request->post('tipo'));
                if($rooms_suggestion['status']){
                    $limit = count($rooms_suggestion['data']);
                    $rooms = $rooms_suggestion['data'];
                }else{
                    return ['status'=>0,'message'=>'Habitación no disponible'];
                }
            }else{
                return ['status'=>0,'message'=>'Habitación no disponible'];
            }
        }
        $Clientes = \app\models\Clientes::find()->andWhere(['email'=>Yii::$app->request->post('email')])->one();
        if(!$Clientes){
            $Clientes = new \app\models\Clientes();
            $Clientes->pasaporte = Yii::$app->request->post('pasaporte');
            $Clientes->nombres = Yii::$app->request->post('nombres');
            $Clientes->email = Yii::$app->request->post('email');
            $Clientes->telefono = Yii::$app->request->post('telefono');
            if(!$Clientes->save()){
                $message = '';
                foreach ($Clientes->getErrors() as $key => $error){
                    $message.= $error[0];
                }
                return ['status'=>0,'message'=>$message];
            }
        }
        if(Yii::$app->request->post('instalaciones') > 1 || $limit > 0){
            $GruposReservas = new \app\models\GruposReservas();
            $GruposReservas->clientes_id = $Clientes->id;
            $GruposReservas->save();
        }
        if($limit == 0){
            $limit = Yii::$app->request->post('instalaciones');
        }
        for ($i=1;$i<=$limit;$i++){
            $item = $rooms[$i-1];
            $room = Instalaciones::get_data($item['id']);
            $option['Iid'] = $room['Iid'];
            $option['Inombre'] = $room['Inombre'];
            $option['ITnombre'] = $room['ITnombre'];
            if(isset($item['fecha_inicio']) && isset($item['fecha_fin'])){
                $option['fecha_inicio'] = $item['fecha_inicio'];
                $option['fecha_fin'] = $item['fecha_fin'];
            }else{
                $option['fecha_inicio'] = Yii::$app->request->post('from');
                $option['fecha_fin'] = Yii::$app->request->post('to');
            }

            $option['value'] = Reservas::calcula_valor_temporada($item['id'], Yii::$app->request->post('from'), Yii::$app->request->post('to'));
            if($valor_a_cobrar = Reservas::calcula_valor_temporada($item['id'], Yii::$app->request->post('from'), Yii::$app->request->post('to')))
                $option['valor'] = number_format($valor_a_cobrar,0,',','.');
            else
                $option['valor'] = 'Valor desconocido';
            $data = $option;
            $booking['status'] = 1;
            $booking['pay_services'] = 'Si';
            $booking['clientes_id_billing'] = $Clientes->id;
            $booking['notes'] = Yii::$app->request->post('notes');
            $booking['send_email'] = false;
            $booking['room'] = [$data['Iid'],$option['fecha_inicio'],$option['fecha_fin']] ;

            if(isset($GruposReservas)){
                $booking['reservas_grupos_id'] = $GruposReservas->id;
            }else{
                $booking['reservas_grupos_id'] = null;
            }
            $booking['created_by'] = 2;
            $result = Reservas::BookRoom($booking);
            if(isset($GruposReservas)){
                $return_id = $GruposReservas->id;
            }else{
                $return_id = $result;
            }
            if($result && (int)$i == (int)Yii::$app->request->post('instalaciones')) {
                $success = true;
            }
        }
        if($success) {
            $query = 'SELECT instalaciones_tipo.nombre as type, instalaciones.nombre as name, reservas.fecha_inicio as checkin_date, reservas.fecha_fin as checkout_date, reservas.valor_temporada as total_amount FROM reservas JOIN instalaciones ON instalaciones.id = reservas.instalaciones_id JOIN instalaciones_tipo ON instalaciones_tipo.id = instalaciones.tipo_instalacion_id where reservas.id = :id';
            return ['status'=>1,'message'=>'El proceso terminó correctamente! Se ha creado la reserva ','id'=>$return_id];
        }

        return ['status'=>0,'message'=>'Error! Las reservas no fueron guardadas. Por favor inténtenlo nuevamente.'];
    }
    /**
     * Default error handler
     * @return array
     */
    public function actionError()
    {
        return ['status'=>0,'message'=>'Algo salió mal, intente nuevamente más tarde.'];
    }

    /**
     * after successful payment mark booking status confirm
     * @return array
     * @throws Exception
     * @throws HttpException
     */
    public function actionBookingConfirm()
    {
        $this->validateRequiredParams(['id']);
        $Reservas = \app\models\Reservas::find()->andWhere(['reservas_grupos_id'=>Yii::$app->request->post('id')])->one();
        if($Reservas){
            $query = "UPDATE `reservas` SET `status` = '2' WHERE `reservas`.`reservas_grupos_id` = :id; ";
        }else{
            $query = "UPDATE `reservas` SET `status` = '2' WHERE `reservas`.`id` = :id; ";
        }
        $result =\Yii::$app->db->createCommand($query)
            ->bindValue(':id' , Yii::$app->request->post('id') )
            ->execute();
        if($result){
            return ['status' => 1,'message'=>'Your booking confirm successfully', 'result' => $result];
        }
        return ['status' => 0,'message'=>'something went\'s wrong contact support for more information', 'result' => $result];
    }

    /**
     * common function for check required parameters in all api
     * @param array $param
     * @return void
     * @throws HttpException
     */
    protected function validateRequiredParams($param = [])
    {
        try {
            $model = \yii\base\DynamicModel::validateData(Yii::$app->request->post(), [
                [$param, 'required'],
            ]);
        }catch (\Exception $e){
            throw new HttpException(401,'Parámetros inválidos');
        }
        if ($model->hasErrors()) {
            $message = '';
            foreach ($model->getErrors() as $key => $error){
                $message.= $error[0];

            }
            throw new HttpException(401,$message);
        }
    }
}

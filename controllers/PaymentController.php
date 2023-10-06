<?php
namespace app\controllers;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\web\Controller;
use app\components\Instalaciones;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use Transbank\Webpay\Configuration;
use Transbank\Webpay\Webpay;
use yii\web\Response;

class PaymentController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    /**
     * Payment gateway callback handel of success or any error while user pay and add payment log
     * @return mixed
     * @throws Exception
     */
    public function actionCallback()
    {
        $this->layout = false;
        if(Yii::$app->request->post('token_ws')){
            $webpay = $this->GetPaymentConfiguration();
            $result = $webpay->getNormalTransaction()->getTransactionResult(Yii::$app->request->post('token_ws'));
            if (isset($result->detailOutput->responseCode) && $result->detailOutput->responseCode === 0) {
                $buy_order_id = $result->detailOutput->buyOrder;
                $buy_order = explode('_',$result->detailOutput->buyOrder);
                $reservas_id = $buy_order[1];

                $valor = $result->detailOutput->amount;
                $descripcion = 'Codigo de Autorizacion:'.$result->detailOutput->authorizationCode;
                if($result->detailOutput->sharesNumber > 0){
                    $descripcion .= 'Número de cuotas '.$result->detailOutput->sharesNumber.' de '.$result->detailOutput->sharesAmount.' cada una';
                }
                $query_log = "INSERT INTO `reservas_pagos` (`id`, `reservas_id`, `fecha_pago`, `formas_pago_id`, `voucher`, `voucher_pic`, `descripcion`, `valor`,`authorization_token`,`authorized_amount`,`nullify_token`, `created_by`, `created_on`) VALUES (NULL, :reservas_id, :payment_date, :formas_pago_id, :voucher, NULL, :descripcion, :valor,:authorization_token, :authorized_amount,NULL, :created_by, :created_on);";
                $Reservas = \app\models\Reservas::find()->andWhere(['reservas_grupos_id'=>$reservas_id])->all();
                if($Reservas){
                    if(Yii::$app->request->get('from') == 'remain'){
                        $Reservas_ids = [];
                        foreach ($Reservas as $key => $value){
                            $Reservas_ids[] = $value->id;
                        }
                        $valor = \app\models\ReservasPagos::find()
                            ->andWhere(['in', 'reservas_id', $Reservas_ids])
                            ->andWhere(['created_by'=>2,'formas_pago_id'=>2])
                            ->sum('valor');
                    }
                    $query = "UPDATE `reservas` SET `status` = '2' WHERE `reservas`.`reservas_grupos_id` = :id; ";
                    $total_amount = $valor;
                    foreach ($Reservas as $key => $reservas){
                        if($total_amount >= $reservas->valor_a_pagar){
                            $total_amount_pagos = $reservas->valor_a_pagar;
                        }else{
                            $total_amount_pagos = $total_amount;
                        }

                        \Yii::$app->db->createCommand($query_log)
                            ->bindValue(':reservas_id' , $reservas->id )
                            ->bindValue(':payment_date' , date('Y-m-d') )
                            ->bindValue(':formas_pago_id' , '2' )
                            ->bindValue(':voucher' ,$buy_order_id)
                            ->bindValue(':descripcion' ,$descripcion)
                            ->bindValue(':valor' , $total_amount_pagos)
                            ->bindValue(':authorization_token',$result->detailOutput->authorizationCode)
                            ->bindValue(':authorized_amount',$result->detailOutput->amount)

                            ->bindValue(':created_by' , '2' )
                            ->bindValue(':created_on' , date('Y-m-d H:i:s') )
                            ->execute();
                        $total_amount = $total_amount - $total_amount_pagos;
                    }
                }else{

                    $query = "UPDATE `reservas` SET `status` = '2' WHERE `reservas`.`id` = :id; ";
                    \Yii::$app->db->createCommand($query_log)
                        ->bindValue(':reservas_id' , $reservas_id )
                        ->bindValue(':payment_date' , date('Y-m-d') )
                        ->bindValue(':formas_pago_id' , '2' )
                        ->bindValue(':voucher' ,$buy_order_id)
                        ->bindValue(':descripcion' ,$descripcion)
                        ->bindValue(':valor' , $valor )
                        ->bindValue(':authorization_token' , $result->detailOutput->authorizationCode )
                        ->bindValue(':authorized_amount',$result->detailOutput->amount)
                        ->bindValue(':created_by' , '2' )
                        ->bindValue(':created_on' , date('Y-m-d H:i:s') )
                        ->execute();
                    if(Yii::$app->request->get('from') == 'remain'){
                        $valor = \app\models\ReservasPagos::find()
                            ->andWhere(['reservas_id'=> $reservas_id])
                            ->andWhere(['created_by'=>2,'formas_pago_id'=>2])
                            ->sum('valor');
                    }
                }
               \Yii::$app->db->createCommand($query)
                    ->bindValue(':id' ,$reservas_id )
                    ->execute();
            } else {
                Yii::$app->session->setFlash('error','Transacción rechazada. El pago no fue realizado. Por favor intente nuevamente. token_ws : '.Yii::$app->request->post('token_ws'));
                if(Yii::$app->request->get('redirect_url')){
                    return $this->redirect(Yii::$app->request->get('redirect_url'));
                }
            }
        }else{
            Yii::$app->session->setFlash('error',"Transacción rechazada. El pago no fue realizado. Por favor intente nuevamente. token_ws : ".Yii::$app->request->post('token_ws'));
            if(Yii::$app->request->get('redirect_url')){
                return $this->redirect(Yii::$app->request->get('redirect_url'));
            }
        }
        $query = 'SELECT instalaciones_tipo.nombre as type,instalaciones_tipo.descripcion as description, instalaciones.nombre as name, reservas.fecha_inicio as checkin_date, reservas.fecha_fin as checkout_date, reservas.valor_a_pagar as total_amount, reservas.status as status, reservas.id as id ,reservas.clientes_id_billing,buy_order_id FROM reservas JOIN instalaciones ON instalaciones.id = reservas.instalaciones_id JOIN instalaciones_tipo ON instalaciones_tipo.id = instalaciones.tipo_instalacion_id';
        $Reservas = \app\models\Reservas::find()->andWhere(['reservas_grupos_id'=>$reservas_id])->one();
        if($Reservas){
            $query .= '  where reservas.reservas_grupos_id = :id';
        }else{
            $query .= '  where reservas.id = :id';
        }
        $reservation_details =\Yii::$app->db->createCommand($query)
            ->bindValue(':id' ,$reservas_id )
            ->queryAll();

        $this->SendInvoiceRecipt($reservation_details,$valor);
        return $this->render('success',['data'=>$reservation_details,'paid_amount'=>$valor]);
    }

    /**
     * Send Invoice recipt to registered user email address
     * @param $reservation_details
     * @param $valor
     * @return mixed
     */
    public function SendInvoiceRecipt($reservation_details,$valor)
    {
        $Clientes = \app\models\Clientes::findOne($reservation_details[0]['clientes_id_billing']);
        if($Clientes){
            $email_content = $this->render('email_recipt',['data'=>$reservation_details,'paid_amount'=>$valor]);
            Yii::$app->mailer->compose()
                ->setHtmlBody($email_content)
                ->setFrom(['info@bahiaisidoro.cl' => 'Bahía Isidoro'])
                ->setTo( $Clientes->email)
                ->setSubject('Bahía Isidoro - Pago exitoso')
                ->send();
        }
    }

    /**
     * common function for Calculate total paid amount of reservation
     * @param $id
     * @return total paid amount
     */
    public function CalculatePaidAmount($id)
    {
        $Reservas = \app\models\Reservas::find()->andWhere(['reservas_grupos_id'=>$id])->all();
        if($Reservas) {
            $Reservas_ids = [];
            foreach ($Reservas as $key => $value) {
                $Reservas_ids[] = $value->id;
            }
           return \app\models\ReservasPagos::find()
                ->andWhere(['in', 'reservas_id', $Reservas_ids])
                //->andWhere(['created_by' => 2, 'formas_pago_id' => 2])
                ->sum('valor');
        }else{
            return \app\models\ReservasPagos::find()
                ->andWhere(['reservas_id'=> $id])
                //->andWhere(['created_by' => 2, 'formas_pago_id' => 2])
                ->sum('valor');
        }
    }
    /**
     * Show payment recipt with booking detail and redirect to payment gateway page
     * @param $id it will be reservation id or reservation group id
     * @return mixed
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function actionIndex($id)
    {
        $this->layout = false;
        $query = 'SELECT instalaciones_tipo.nombre as type,instalaciones_tipo.descripcion as description, instalaciones.nombre as name, reservas.fecha_inicio as checkin_date, reservas.fecha_fin as checkout_date, reservas.valor_a_pagar as total_amount, reservas.status as status, reservas.id as id,buy_order_id FROM reservas JOIN instalaciones ON instalaciones.id = reservas.instalaciones_id JOIN instalaciones_tipo ON instalaciones_tipo.id = instalaciones.tipo_instalacion_id';
        $Reservas = \app\models\Reservas::find()->andWhere(['reservas_grupos_id'=>$id])->one();
        if($Reservas){
            $query .= '  where reservas.reservas_grupos_id = :id';
        }else{
            $query .= '  where reservas.id = :id';
        }
        $reservation_details =\Yii::$app->db->createCommand($query)
            ->bindValue(':id' ,$id )
            ->queryAll();
        if(count($reservation_details) == 0){
            return $this->render('error');
        }
        if(Yii::$app->request->isPost && Yii::$app->request->post('price')){
            $price = Yii::$app->request->post('price');
            if(Yii::$app->request->post('price') == 'custom'){
                $price = Yii::$app->request->post('custom_price');
            }
            //echo "<pre>";print_r($_REQUEST);exit;
            $payment_data = $this->GetPaymentToken($reservation_details[0]['buy_order_id'],$price,'',Yii::$app->request->getUrl());
            
            return $this->render('redirect_payment',['payment_data'=>$payment_data,'paid_amount'=> $this->CalculatePaidAmount($id)]);
        }
        if($reservation_details[0]['status'] == 2){
            return $this->render('success',['data'=>$reservation_details,'paid_amount'=> $this->CalculatePaidAmount($id)]);
        }
        return $this->render('index',['data'=>$reservation_details]);
    }

    /**
     * this is payment link from email
     * Show remain payment and pay option
     * redirect to payment gateway
     * @param $token
     * @return mixed
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function actionStatus($token)
    {
        $this->layout = false;
        $id = Yii::$app->security->unmaskToken($token);
        $Reservas = \app\models\Reservas::findOne($id);
        if(!$Reservas){
            Yii::$app->session->setFlash('error',"Token de reserva no válido.");
            return $this->render('message');
        }
        $link_created_date = strtotime('+24 hour', strtotime($Reservas->payment_link_created_at));
        $curent_date_time = strtotime(date('Y-m-d H:i:s'));
        if($link_created_date < $curent_date_time){
            Yii::$app->session->setFlash('error',"Este enlace ha caducado, póngase en contacto con el servicio de asistencia para volver a generar el enlace de pago.");
            return $this->render('message');
        }
        $query = 'SELECT instalaciones_tipo.nombre as type,instalaciones_tipo.descripcion as description, instalaciones.nombre as name, reservas.fecha_inicio as checkin_date, reservas.fecha_fin as checkout_date, reservas.valor_a_pagar as total_amount, reservas.status as status, reservas.id as id,buy_order_id FROM reservas JOIN instalaciones ON instalaciones.id = reservas.instalaciones_id JOIN instalaciones_tipo ON instalaciones_tipo.id = instalaciones.tipo_instalacion_id';
        $query .= '  where reservas.id = :id';
        $reservation_details =\Yii::$app->db->createCommand($query)
            ->bindValue(':id' ,$id )
            ->queryAll();
        if(Yii::$app->request->isPost){
            $total_amount = $reservation_details[0]['total_amount'];
            $paid_amount = $this->CalculatePaidAmount($id);
            $remain_amount = $total_amount - $paid_amount;
            $payment_data = $this->GetPaymentToken($reservation_details[0]['buy_order_id'],$remain_amount,'remain',Yii::$app->request->getUrl());
            return $this->render('redirect_payment',['payment_data'=>$payment_data]);
        }
        $paid_amount = \app\models\ReservasPagos::find()
            ->andWhere(['reservas_id'=>$id])
            ->sum('valor');
        return $this->render('remain',['data'=>$reservation_details,'paid_amount'=>$paid_amount]);
    }
    /**
     * common function for generate payment token and webpay redirect url
     * @param $id
     * @param $price
     * @param string $from
     * @param $redirect_url
     * @return array|Response
     * @throws InvalidConfigException
     */
    public function GetPaymentToken($id,$price,$from = '',$redirect_url)
    {
        $data = [];
        $webpay = $this->GetPaymentConfiguration();
        $amount = ceil($price);
        $buyOrder = $id;
        $sessionId = uniqid();
        $urlReturn = Yii::$app->urlManager->createAbsoluteUrl(['payment/callback?type=return&from='.$from.'&redirect_url='.$redirect_url]);
        $urlFinal  = Yii::$app->urlManager->createAbsoluteUrl(str_replace('bookisityapi/','',$redirect_url));
        $result = $webpay->getNormalTransaction()->initTransaction($amount, $buyOrder, $sessionId, $urlReturn, $urlFinal);
        //echo "<pre>";print_r($result);exit;
        /*
        if(isset($result['error']) && !empty($result['error'])){
            throw new NotFoundHttpException(Yii::t('app', $result['error']));
        }
        */
        
        if (!empty($result->token) && isset($result->token)) {
            $data['token'] = $result->token;
            $data['url'] = $result->url;
        } else {
            return $this->redirect(Yii::$app->request->getUrl());
        }
        return $data;
    }

    /**
     * common function for configure payment
     * @return Webpay
     */
    public function GetPaymentConfiguration()
    {
        $configuration = new Configuration();
        $configuration->setEnvironment("PRODUCCION");
        $configuration->setCommerceCode(597035231773);
        $configuration->setPrivateKey(
            "-----BEGIN RSA PRIVATE KEY-----\n" .
            "MIIEowIBAAKCAQEAxPHoQdguDGl6MhVMrrEQR7UbyMadE4Z9XeNbTdEhBehZ8cbn\n" .
            "U9G1QfoCz5bZodThaGFqix8TdbskZ6m/1rNFwUovYlHIDh5kOlAskrNvMDXAxWGT\n" .
            "JMdLe9WTYOb3nz2MB+hLMY57KGJSvLCQRlmAWE2M29cX0E8TKsLM9bwrUCYeavHh\n" .
            "V35kI02abp54ZtiUKoE/aM1DBTK6Aa1XZEKS1f853RFKgN63pW/m8BvTTtG/2agM\n" .
            "MJw3oy1S/fMy12bDK+iQssZPkVVtYoGIekTuVfgob59wsDPEmHP/71yr4RFeGah5\n" .
            "xBv79i03CBUzcIIEQqNz0vFMRV4vhcYjNma8wQIDAQABAoIBAQDDlWmOWl4AvY84\n" .
            "xaZNplIApH9fOL8tcNZ3sx4tfY5KC6GnVlzNBOn5B4xbE/g1mu/vdS8V0lrFBID2\n" .
            "4cE+OvL/Lek4vvbp7oyizJQ3bDLzsa4rVueGEtWHuWaPSVCIt9qkz7A9Gr58MIjy\n" .
            "EnZ1JtUq3HkSqd1gZecnBCX/tEtfX30G3X3IDUz6FReqqC+A6uuc72sIqwHjAIkA\n" .
            "RxB2lByG69dzSpSY/XDDAhwzLdq3rDMgqmUbNDg4orpUJuZGBQME0u8kuximMajF\n" .
            "ZTf1yn2Yx4g3mOmuuVk9p7i+N+xejxrHwRBrh+R5Uhx9rLqv+lm9igv2jEqP4pss\n" .
            "4FHzFMkBAoGBAO3Jn4+yyiBXkdHYH238jRdlbYrDLyNG6iz4g7O/6EWB1pmZFUPP\n" .
            "M1oS2gyB9PMWGoRI16DSjeV1tNjXKVS+mWwGx7GO3MN5GG9ofjch376jVRvj3jOw\n" .
            "yxHbr3s7+6Cuhi8CC4kYhn8iFD9kTn+8xviN2zZxaSVn6SIR8y7owlZZAoGBANQH\n" .
            "eCfNSNUU3ti/RzGmd02Z8IKJDaYZfy17o7JoyIzhVYUo/lbjsabrpDiXHSUk4UsH\n" .
            "QAKfhJOMCH11wA5ZE4oNLJ/sobPdKmIn1jxkY+oQa4F7Fq6UUJQv0rNXyqsaZRJ2\n" .
            "NBtmbxUDljr+6mZee33GS19pBR5mVY7IsU4QuhypAoGAHIs2by58GvPIGlOCOla1\n" .
            "rRhM1PpnHyn1FF7kmGAyBp32X8vDhLdLp8VZjWTQPZnqpvSDhdeglunRQrJZUMXs\n" .
            "bs5FjGfk0kYoC7+UXxPe4uiCX+2zj6rqRYOEhhuGMhyhGOV68wMRqhMyMQXecnD7\n" .
            "xXxp0xg8EfRuRNu4wGnKYkkCgYAKguhJCNtQfP1jP3BXHMqTVUtgHU1I68CrT5LY\n" .
            "+Grg2Rb1SAf75MPc45e7mno+aiqlHpHkz2WyLuII3jqMO4xFbsvEjeWiVheQ0CrF\n" .
            "ybBOXUwHGkQQmZe5EPngHD0W6HMUTDnfFd/x6cCb4iFau9phbOA1ta4kSKx7LKXl\n" .
            "mdywKQKBgDIWCSm8JCblOT9EPA09hXit8vKVE8pFB3D7SeprMT+843/objVAOPFh\n" .
            "2LY7A6fcNi7na6epow9slP0f6MWuTwCya3tXNSnkArUXj2c7cCRkfsLKON/HSQ+e\n" .
            "0O9uX5vJpAWjcd8UTab2Sdz68njouCqVGE2X5BBxQ7xyGuZsPNdi\n" .
            "-----END RSA PRIVATE KEY-----\n"
        );
        $configuration->setPublicCert(
            "-----BEGIN CERTIFICATE-----\n" .
            "MIICqjCCAZICCQCM+DnyQ4lybDANBgkqhkiG9w0BAQUFADAXMRUwEwYDVQQDDAw1\n" .
            "OTcwMzUyMzE3NzMwHhcNMjAwNTEzMDIxODM2WhcNMjQwNTEyMDIxODM2WjAXMRUw\n" .
            "EwYDVQQDDAw1OTcwMzUyMzE3NzMwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEK\n" .
            "AoIBAQDE8ehB2C4MaXoyFUyusRBHtRvIxp0Thn1d41tN0SEF6FnxxudT0bVB+gLP\n" .
            "ltmh1OFoYWqLHxN1uyRnqb/Ws0XBSi9iUcgOHmQ6UCySs28wNcDFYZMkx0t71ZNg\n" .
            "5vefPYwH6EsxjnsoYlK8sJBGWYBYTYzb1xfQTxMqwsz1vCtQJh5q8eFXfmQjTZpu\n" .
            "nnhm2JQqgT9ozUMFMroBrVdkQpLV/zndEUqA3relb+bwG9NO0b/ZqAwwnDejLVL9\n" .
            "8zLXZsMr6JCyxk+RVW1igYh6RO5V+Chvn3CwM8SYc//vXKvhEV4ZqHnEG/v2LTcI\n" .
            "FTNwggRCo3PS8UxFXi+FxiM2ZrzBAgMBAAEwDQYJKoZIhvcNAQEFBQADggEBABq8\n" .
            "iskbxj3DabHZFiHx2PTPfYjq09RWkWdSsPDu/4+WQvAAKrEkpbZU+dhg4Mxt3NlE\n" .
            "MVARRgJzuylgYsbS8oMF5utlalxAAoKvMX21DjJuPsh5rMHy1kf3dBnPWnWMTBb9\n" .
            "HGUJLPv2ghEiTvu46E1iuvxskP+TKSTQKZFAQ4yJpI8nbiovNzopYnSQZ5GeKm4h\n" .
            "n7r2se3Zi3eLjss0AWjidtlb14VIxDNz5AwNXc4vAQmj2rKbwtR0JaV+NlDspE71\n" .
            "xrR4P7fdHvcOiS6CgUYadu5gNeY7UrLN62ya+IefLxqTVjdDWuQDPod/Zdzgjrdk\n" .
            "EHfnhQiKYQQ3E6zcrpA=\n" .
            "-----END CERTIFICATE-----\n"
        );
        $configuration->setWebpayCert(Webpay::defaultCert());

        $webpay = new Webpay($configuration);
        return $webpay;
    }
}

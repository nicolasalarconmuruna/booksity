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
                Yii::$app->session->setFlash('error','Transacción rechazada. El pago no fue realizado. Por favor intente nuevamente.');
                if(Yii::$app->request->get('redirect_url')){
                    return $this->redirect(Yii::$app->request->get('redirect_url'));
                }
            }
        }else{
            Yii::$app->session->setFlash('error',"Transacción rechazada. El pago no fue realizado. Por favor intente nuevamente.");
            if(Yii::$app->request->get('redirect_url')){
                return $this->redirect(Yii::$app->request->get('redirect_url'));
            }
        }
        $query = 'SELECT instalaciones_tipo.nombre as type,instalaciones_tipo.descripcion as description, instalaciones.nombre as name, reservas.fecha_inicio as checkin_date, reservas.fecha_fin as checkout_date, reservas.valor_temporada as total_amount, reservas.status as status, reservas.id as id ,reservas.clientes_id_billing FROM reservas JOIN instalaciones ON instalaciones.id = reservas.instalaciones_id JOIN instalaciones_tipo ON instalaciones_tipo.id = instalaciones.tipo_instalacion_id';
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
                ->setSubject('Bookisity - Payment Success')
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
                ->andWhere(['created_by' => 2, 'formas_pago_id' => 2])
                ->sum('valor');
        }else{
            return \app\models\ReservasPagos::find()
                ->andWhere(['reservas_id'=> $id])
                ->andWhere(['created_by' => 2, 'formas_pago_id' => 2])
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
        $query = 'SELECT instalaciones_tipo.nombre as type,instalaciones_tipo.descripcion as description, instalaciones.nombre as name, reservas.fecha_inicio as checkin_date, reservas.fecha_fin as checkout_date, reservas.valor_temporada as total_amount, reservas.status as status, reservas.id as id FROM reservas JOIN instalaciones ON instalaciones.id = reservas.instalaciones_id JOIN instalaciones_tipo ON instalaciones_tipo.id = instalaciones.tipo_instalacion_id';
        $Reservas = \app\models\Reservas::find()->andWhere(['reservas_grupos_id'=>$id])->one();
        if($Reservas){
            $query .= '  where reservas.reservas_grupos_id = :id';
        }else{
            $query .= '  where reservas.id = :id';
        }
        $reservation_details =\Yii::$app->db->createCommand($query)
            ->bindValue(':id' ,$id )
            ->queryAll();
        if(Yii::$app->request->isPost && Yii::$app->request->post('price')){
            $price = Yii::$app->request->post('price');
            if(Yii::$app->request->post('price') == 'custom'){
                $price = Yii::$app->request->post('custom_price');
            }
            $payment_data = $this->GetPaymentToken($id,$price,'',Yii::$app->request->getUrl());
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
        $link_created_date = strtotime('+48 hour', strtotime($Reservas->payment_link_created_at));
        $curent_date_time = strtotime(date('Y-m-d H:i:s'));
        if($link_created_date < $curent_date_time){
            Yii::$app->session->setFlash('error',"Este enlace ha caducado, póngase en contacto con el servicio de asistencia para volver a generar el enlace de pago.");
            return $this->render('message');
        }
        $query = 'SELECT instalaciones_tipo.nombre as type,instalaciones_tipo.descripcion as description, instalaciones.nombre as name, reservas.fecha_inicio as checkin_date, reservas.fecha_fin as checkout_date, reservas.valor_temporada as total_amount, reservas.status as status, reservas.id as id FROM reservas JOIN instalaciones ON instalaciones.id = reservas.instalaciones_id JOIN instalaciones_tipo ON instalaciones_tipo.id = instalaciones.tipo_instalacion_id';
        $query .= '  where reservas.id = :id';
        $reservation_details =\Yii::$app->db->createCommand($query)
            ->bindValue(':id' ,$id )
            ->queryAll();
        if(Yii::$app->request->isPost){
            $total_amount = $reservation_details[0]['total_amount'];
            $paid_amount = $this->CalculatePaidAmount($id);
            $remain_amount = $total_amount - $paid_amount;
            $payment_data = $this->GetPaymentToken($id,$remain_amount,'remain',Yii::$app->request->getUrl());
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
        $buyOrder = date('YmdHi').'_'.$id;
        $sessionId = uniqid();
        $urlReturn = Yii::$app->urlManager->createAbsoluteUrl(['payment/callback?type=return&from='.$from.'&redirect_url='.$redirect_url]);
        $urlFinal  = Yii::$app->urlManager->createAbsoluteUrl(str_replace('bookisityapi/','',$redirect_url));
        $result = $webpay->getNormalTransaction()->initTransaction($amount, $buyOrder, $sessionId, $urlReturn, $urlFinal);

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
            "MIIEogIBAAKCAQEA0A22q0zDukFqYcFOeU2tYM0pyk7vN2ENBiuO/IAoCTU/cQUi\n" .
            "KcSJ/fqIVcInobM8+uSnvhNTaZmRwW38qM27EUvK6I+n19vd6PHSi1sw1104z4c5\n" .
            "N8OlDt1/zEXmhuCOkZ/KbHs7tsrXk2QeOH/7RGI2463bNNfn9X9NraYcnhcu56Gs\n" .
            "C187hkYvWev/O6X2sY+fJpV3yY3jvl3z8OQ5Fggdeg0W7Jdxu1LJlwEeFw5OcTxA\n" .
            "HP0sJNBzaaRcbf3qgs2mUxpLhe9ox2nlxT4Jpb9rzmEqw2V8lDXy/2k0rGOmt2n7\n" .
            "umtSlfdTIFoKFut2dnyeixxCCfdG6qkmtHogzQIDAQABAoIBABuT5cW5DCyxJRfG\n" .
            "Fs/Pcw7kwwhVBDJ0A9Twiyh/GE94JmulwYyFx8DJp66uaLBvYMLk8jMovqK9v2tD\n" .
            "V/1MH+LACCpheF8NftG01DKyqLuzWKdxhi0VGtjolVsPXOo988frxVoxz42AP6kC\n" .
            "2Vql5DmBR0NQtUDA6bdJ45MD9MEjll5Nh2phof6afLKPGxIsZeibyJWdH5VdVF3+\n" .
            "4SFr+gSbdotw28Mu6HVoxHKDsmfWM9IY6VhBNjMUgkjh0Xx3b+X+uRWlPLmd9JOl\n" .
            "GiQKEltYuELpxTpbOUczHGqib+WB71fTZCaoPACLhzPOOdnwuJdO5c2lBIP/uOUq\n" .
            "/9jogIECgYEA5zzic60p8kvwBswpFFXz1GEOcXo4RhPTt8mRB+9nLfwhC9zAgIBd\n" .
            "GWAxc5PU6CJlY8xpDhYHdpyD1x751GQQEBe/nArb6XaRTbLQL2Dz8UTxo7B95Bem\n" .
            "QkEWCwSaBKl6cFsC6JLrjsnu6216Gj08rXkeqhvKHp/Be6gC1buUi60CgYEA5lVC\n" .
            "bQhGMLteV45TvZ/H4UaGCQBy2StuJhUP5uYULbXem0xGsA9SIy8YrR2LIkEk7v8Y\n" .
            "76/VA6WhPFP5tGG4cavmBLsqIG+nPjCxtPGPIVMRobMXDKvpFcUwXIJIu5vzPH9j\n" .
            "+zG+OGniZafPpoaXhKZFUK/zzWS4ycpFRfugjaECgYAC74LwdQJTUSN68pyS5YRy\n" .
            "7ciBKEwOl7HYY3az4xYsP0csH2FSQE7uQ4pdLUNGrykaWz36L81odBQ4ZuxFBgAu\n" .
            "NB76nCiujhLKKbr63wA5z+ZBbbwraSFzNeBRw30xEfW792vSCAt1hJrD4l/qdVyP\n" .
            "1znMbw3h1aVfLILcs8TvAQKBgFPYu5qXRX3d50T2MbO4o3l1Q7upJyW5Mpq4VhaW\n" .
            "sMfHCeb8iEr0+NCIB3KVa52nmztJL5mpJ2DxfVVJuH+ahxsSGWSlgXtXSclQzo/w\n" .
            "00qtQ6DaYcyiE/Jx2t4CK1noNk5SjWHWxMkiemDJCsUy/5sxL9BkjNq7DK2gbUFB\n" .
            "jTuhAoGAPFjVn/sArXW9GHOpdCqVaNLX5I4Ki/BZ+F1qknlEOW5YVyNGxMkoZX7o\n" .
            "xkTpj63MfWU/9yd5jd+gDzqBxXnj7nP+LOni0f7JUluiPimcsi4f4H/fkuHW2icm\n" .
            "P+zZ4LmFQ1ZJzh0zr6wKIOoCq8H+sy2SmbQIYhP9TxjM7+c3ZIE=\n" .
            "-----END RSA PRIVATE KEY-----\n"
        );
        $configuration->setPublicCert(
            "-----BEGIN CERTIFICATE-----\n" .
            "MIIDPzCCAicCFEpS3c4guvQt/CqBAXm3qOJ8klChMA0GCSqGSIb3DQEBCwUAMFwx\n" .
            "CzAJBgNVBAYTAkFVMRMwEQYDVQQIDApTb21lLVN0YXRlMSEwHwYDVQQKDBhJbnRl\n" .
            "cm5ldCBXaWRnaXRzIFB0eSBMdGQxFTATBgNVBAMMDDU5NzAzNTIzMTc3MzAeFw0y\n" .
            "MDAzMjYxMzQyNTdaFw0yNDAzMjUxMzQyNTdaMFwxCzAJBgNVBAYTAkFVMRMwEQYD\n" .
            "VQQIDApTb21lLVN0YXRlMSEwHwYDVQQKDBhJbnRlcm5ldCBXaWRnaXRzIFB0eSBM\n" .
            "dGQxFTATBgNVBAMMDDU5NzAzNTIzMTc3MzCCASIwDQYJKoZIhvcNAQEBBQADggEP\n" .
            "ADCCAQoCggEBANANtqtMw7pBamHBTnlNrWDNKcpO7zdhDQYrjvyAKAk1P3EFIinE\n" .
            "if36iFXCJ6GzPPrkp74TU2mZkcFt/KjNuxFLyuiPp9fb3ejx0otbMNddOM+HOTfD\n" .
            "pQ7df8xF5obgjpGfymx7O7bK15NkHjh/+0RiNuOt2zTX5/V/Ta2mHJ4XLuehrAtf\n" .
            "O4ZGL1nr/zul9rGPnyaVd8mN475d8/DkORYIHXoNFuyXcbtSyZcBHhcOTnE8QBz9\n" .
            "LCTQc2mkXG396oLNplMaS4XvaMdp5cU+CaW/a85hKsNlfJQ18v9pNKxjprdp+7pr\n" .
            "UpX3UyBaChbrdnZ8noscQgn3RuqpJrR6IM0CAwEAATANBgkqhkiG9w0BAQsFAAOC\n" .
            "AQEAfba1gjhsnjw81u8isz9WWtktJt/SE8Ftkw9CJNFfUDOLB8ermQvjEtkYe3ln\n" .
            "ZVahbdCOhLNOufFFphvGIYkN+cPxv0sfdsjfc9Png9RrVPJbDjTG3/c0V36Zyn+V\n" .
            "sN+VLF0f8jGlG0qodEH91GAE0T6jGm2MGbMvrlBjfQGMdZPj9LJAWWty7R08r6ND\n" .
            "sVRwyKJdrEXETEIDcj3UIBUSE4qjf0NQFsgOnF2Bgsb63URm3mWvbNIhy6u7heU7\n" .
            "PVjNBcR0OvGAsMz3autq1ucwD+puzRy/U6hSMGpECQQx9DBRAjz2rcPv3bjM7U9d\n" .
            "ZLFJ99HG0/QnFwDjz17RcZBufA==\n" .
            "-----END CERTIFICATE-----\n"
        );
        $configuration->setWebpayCert(Webpay::defaultCert());

        $webpay = new Webpay($configuration);
        return $webpay;
    }
}

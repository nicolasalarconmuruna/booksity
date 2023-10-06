<?php
use yii\helpers\Url;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>
        Bahia Isidoro
    </title>
    <meta name="description" content="">
    <meta name="keywords" content="">
    <meta name="author" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style type="text/css">
        h1{
            margin:0;
        }
        hr {
            border-top: 1px solid #c7c9d6;
        }

        .table-total tr td{
            padding:3px 15px;
        }
        .table-total tr td span{
            margin-left:5px;
            display: inline-block;
        }
        .table-top tr td{
            padding:15px 15px 7px;
        }
        .banner h1{
            font-weight: 700;
            font-size: 22px;
        }
        .list-inline{
            list-style: none
        }

        .separator{

            height:20px;
        }
        .separator-xs{
            height: 10px;
        }
        .separator-xxs{
            height: 3px;
        }
        .title{
            font-size: 22px;
        }
        .final-amount span{
            font-size:17px;
        }
        .main-section-payment{
            max-width: 700px;
            margin: 0px auto;
        }
        h1,h2,h3,h4,h5,th{
            font-weight:600;
        }
        .table-inside{
            width:100%;
        }
        .table-inside thead {
            background: #e7852a;
            color: #fff;
        }
        .table-inside thead th,.table-inside td{
            padding: .5rem 1rem;
            font-size: 14px;
        }
        .table-footer{
            padding: 0 15px;
        }
        .p-0{
            padding:0 !important;
        }
        body, table, td, a{color:#292727;-webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;} /* Prevent WebKit and Windows mobile changing default text sizes */
        table, td{mso-table-lspace: 0pt; mso-table-rspace: 0pt;} /* Remove spacing between tables in Outlook 2007 and up */
        img{-ms-interpolation-mode: bicubic;} /* Allow smoother rendering of resized image in Internet Explorer */
        /* RESET STYLES */
        img{border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none;}
        table{border-collapse: collapse !important; }
        body{height: 100% !important; padding: 0 !important; width: 100% !important;    font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";}
        /* iOS BLUE LINKS */
        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }
        /***outlook***/
        .btn-theme{

            /* border-radius: 30px;*/
        }
        body{

            margin:20px 0px 20px 0px;
            padding: 0 !important;


        }
        strong{
            font-weight: 600;
        }
        .main-section-payment{
            max-width: 600px;
            margin:0px auto;
            text-align:center;
            background: #f7f7f7;

            box-shadow: -1px 3px 20px rgba(199, 197, 197, 0.4);
            -webkit-box-shadow: -1px 3px 20px rgba(199, 197, 197, 0.4);
            -moz-box-shadow: -1px 3px 20px rgba(199, 197, 197, 0.4);
            -ms-box-shadow: -1px 3px 20px rgba(199, 197, 197, 0.4);
            -o-box-shadow: -1px 3px 20px rgba(199, 197, 197, 0.4);
            border: 1px solid #dedede;
        }
        .logo{
            height:80px;
        }
        table {border-collapse:separate;}
        a, a:link, a:visited {text-decoration: none; color: #00788a;}

        a:hover {text-decoration: none;}
        h2,h2 a,h2 a:visited,h3,h3 a,h3 a:visited,h4,h5,h6,.t_cht {color:#000 !important;}
        .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td {line-height: 100%;}
        .ExternalClass {width: 100%;}
        .w-100{
            width:100%;
        }
        .payment-success {
            height: 80px;
        }
        .btn-theme-back {
            border: 1.5px solid #bbb;
            color: #525050 !important;
            background: transparent;
        }
        .btn {
            border-radius: 0;
            color: #fff;
            min-width: 93px;
            font-size: 14px;
            padding: .5rem .75rem;
            font-weight: 600;
        }
        .btn-theme {
            background: #e7852a;
            color:#fff !important;
        }
        .btn-theme:hover {
            background: #de5b2c;
            transition-duration: .2s;
        }
        .btn-theme-back:hover {
            border: 1.5px solid #e7852a;
            color: #e7852a;
        }
        .text-theme{
            color:#e7852a;
            font-weight: 500;
        }
        h4 {
            font-size: 1.3rem !important;
            margin:0;
        }
        h5{
            font-size: 1rem;
            margin:0;
        }

    </style>
</head>

<body>

<section class="main-section-payment" >
    <div class="" id="printDiv">

        <table border="0" cellpadding="0" cellspacing="0" width="100%" class="table-top">
            <tbody>
            <tr>
                <td align="left" style=""><img src="<?=Url::base(true); ?>/img/logo.png" class="logo" alt="" title="" style=/>
                </td>
                <td align="right">
                    <h5>Número de Reserva : <span class="font-bold" id="order-id"><?= $data[0]['id']; ?></span></h5>
                </td>
            </tr><!----->
            </tbody>
        </table>
        <table class="w-100">
            <tbody>
            <hr/>
            <tr>
                <td class="separator"></td><!--separator---->
            </tr>
            <tr>
                <td align="center">
                    <img src="<?=Url::base(true); ?>/img/payment-success.gif" class="payment-success">
                </td>
            </tr><!---->
            <tr>
                <td>
                <td class="separator-xs"></td><!--separator---->
                </td>
            </tr>
            <tr>
                <td>
                    <h4 class="mt-2">Su pago fue exitoso</h4>
                </td>
            </tr>
            <tr>
                <td>
                    <p class="description mt-2"><?=strip_tags($data[0]['description'])?></p>
                </td>
            </tr>
            <tr><td class="separator-xs"> </td></tr><!----separator---->

            <tr>
                <td>
                    <hr/>
                </td>
            </tr>
            <tr>
                <td align="left">
                    <h5 style="padding:0 15px;">Detalles de la Compra</h5>
                </td>
            </tr>
            <tr><td class="separator-xs"> </td></tr><!----separator---->
            <tr><td class="separator-xs"> </td></tr><!----separator---->
            </tbody>
        </table>
        <table class="table table-inside">
            <thead class="">
            <tr>
                <th scope="col" align="left">Tipo</th>
                <th scope="col" align="left">Instalacion</th>
                <th scope="col" align="left">Check In</th>
                <th scope="col" align="left">Check Out</th>
                <th scope="col"  align="right" class="text-right">Valor</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $total_amount = 0;
            foreach ($data as $key => $value){
            $total_amount = ceil($total_amount+$value['total_amount']);
            ?>
            <tr>
                <td id="type-text" align="left"><?= $value['type']; ?></td>
                <td id="name-text" align="left"><?= $value['name']; ?></td>
                <td id="checkin-text" align="left"><?= Yii::$app->formatter->asDate($value['checkin_date']); ?></td>
                <td id="checkout-text" align="left"><?= Yii::$app->formatter->asDate($value['checkout_date']); ?></td>
                <td class="text-right" id="total-amount" align="right">$<?= (int)$value['total_amount']; ?></td>
            </tr>
            <?php } ?>

            </tbody>
        </table><!------>
        <table class="w-100 table-total">
            <tbody>
            <tr><td class="p-0"><hr/> </td></tr><!----separator---->
            <tr><td class="separator-xs"></td></tr><!--separator---->
            <tr>
                <td align="right">Total: <span class="text-theme">$<?=(int)$total_amount; ?></span></td>
            </tr>
            <tr><td class="separator-xxs"></td></tr><!--separator---->
            <tr>
                <td align="right">Monto Pagado:
                    <span class="text-theme">$<?=(int)$paid_amount?></span></td>
            </tr>
            <tr><td class="separator-xxs"></td></tr><!--separator---->
            <tr class="final-amount" >
                <td align="right"><strong>Monto por Pagar:
                        <span class="text-theme"><?=(int)($total_amount-$paid_amount)?></span></strong></td>
            </tr>
            <tr>
                <td class="separator-xs"></td>
            </tr>
            <!--separator---->
            </tbody>
        </table>
        </table>
    </div>
</section>
</body>

</html>
<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;
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

    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
    <link rel="stylesheet" href="<?=Yii::getAlias('@web')?>/css/site.css" >
    <script
        src="https://code.jquery.com/jquery-2.2.4.min.js"
        integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44="
        crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
</head>

<body>
<div class="loader"></div>
<section class="d-flex align-items-center h-100vh main-section-payment" >
    <div class="container" id="printDiv">
        <?php if (Yii::$app->session->hasFlash('success')): ?>
            <div class="alert alert-success alert-dismissable">
                <button aria-hidden="true" data-dismiss="alert" class="close" type="button">×</button>
                <?= Yii::$app->session->getFlash('success') ?>
            </div>
        <?php endif; ?>

        <?php if (Yii::$app->session->hasFlash('error')): ?>
            <div class="alert alert-danger alert-dismissable">
                <button aria-hidden="true" data-dismiss="alert" class="close" type="button">×</button>
                <?= Yii::$app->session->getFlash('error') ?>
            </div>
        <?php endif; ?>
        <div class="div-inside">
            <div class="d-flex flex-md-row flex-column align-items-center justify-content-md-between justify-content-center div-padding">
                <img src="<?=Yii::getAlias('@web')?>/img/logo.png" class="logo"/>
                <div class="mt-md-0 mt-2">
                    <h5>Número de Reserva : <span class="font-bold" id="order-id">#<?= $data[0]['id']; ?></span></h5>
                </div>
            </div><!----->
            <div>
                <div class="mx-auto text-center border-top border-bottom div-padding">
                    <p class="description mt-2"><?=strip_tags($data[0]['description'])?></p>
                </div><!------->
                <div class="div-padding">
                    <h5>Detalles de la Compra</h5>
                </div>
            </div>
            <table class="table table">
                <thead>
                <tr>
                    <th scope="col">Tipo</th>
                    <th scope="col">Instalación</th>
                    <th scope="col">Check In</th>
                    <th scope="col">Check Out</th>
                    <th scope="col" class="text-right">Valor</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $total_amount = 0;
                foreach ($data as $key => $value){
                    $total_amount = ceil($total_amount+$value['total_amount']);
                    ?>
                    <tr>
                        <td id="type-text"><?= $value['type']; ?></td>
                        <td id="name-text"><?= $value['name']; ?></td>
                        <td id="checkin-text"><?= Yii::$app->formatter->asDate($value['checkin_date']); ?></td>
                        <td id="checkout-text"><?= Yii::$app->formatter->asDate($value['checkout_date']); ?></td>
                        <td class="text-right" id="total-amount">$<?= (int)$value['total_amount']; ?></td>
                    </tr>
                <?php } ?>

                </tbody>

            </table><!---->
            <ul class="list-inline grand-total-div text-right border-top">
                <li class=""><span>Total:</span><span class="grand-total color-theme" id="grand-total">$<?= (int)$total_amount; ?></span></li>

                <li class="mt-1"><span>Monto Pagado:</span><span class="grand-total color-theme">
                        $<?= (int)$paid_amount; ?></span></li>
                <li class="mt-2"><span class="font-bold">Monto por Pagar:</span><span class="grand-total gg-total color-theme">$<?=(int)$total_amount-$paid_amount ?></span></li>
            </ul>

            <div class="div-padding align-items-center justify-content-between border-top" align="right">
                <?php $form = ActiveForm::begin(); ?>
                <?php
                    if(($total_amount-$paid_amount) > 0){
                ?>
                <button type="submit" class="btn btn-theme" >Pagar</button>
                <?php }?>
                <?php ActiveForm::end(); ?>

            </div>
        </div>
    </div>
</section>
<script type="text/javascript">
    $(window).load(function(){
        $('.loader').fadeOut();
    });
    function printDiv(divName) {
        var printContents = document.getElementById(divName).innerHTML;
        var originalContents = document.body.innerHTML;

        document.body.innerHTML = printContents;

        window.print();

        document.body.innerHTML = originalContents;
    }
</script>
</body>

</html>
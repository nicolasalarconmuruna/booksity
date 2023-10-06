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
    <link rel="stylesheet" href="css/style.css" >
    <script
        src="https://code.jquery.com/jquery-2.2.4.min.js"
        integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44="
        crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
</head>

<body>
<section class="d-flex align-items-center h-100vh main-section-payment">

    <div class="container">
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
            <?php if(!empty($data)){ ?>
            <hr class="m-0">
            <div align="center" class="py-3 py-md-4 px-3">
                <h5>Detalles de la Compra</h5>
                <p class="description mt-2"><?=strip_tags($data[0]['description'])?></p>
            </div>
<div class="table-parent">
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th scope="col" >Tipo</th>
                    <th scope="col" >Instalación</th>
                    <th scope="col" >Check In</th>
                    <th scope="col" >Check Out</th>
                    <th scope="col" class="text-right">Valor</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $total_amount = 0;
                    foreach ($data as $key => $value){
                        $total_amount = $total_amount+$value['total_amount'];
                ?>
                <tr>
                    <td id="type-text"><?= $value['type']; ?></td>
                    <td id="name-text"><?= $value['name']; ?></td>
                    <td id="checkin-text"><?= Yii::$app->formatter->asDate($value['checkin_date']); ?></td>
                    <td id="checkout-text"><?= Yii::$app->formatter->asDate($value['checkout_date']); ?></td>
                    <td class="text-right" id="total-amount"><?=(int)$value['total_amount'];?></td>
                </tr>
                <?php } ?>
                </tbody>

            </table><!---->
        </div>
                <?php $form = ActiveForm::begin(); ?>
                <div class="select-payment-div border-top d-md-flex align-items-center justify-content-center">
                    <!--  <h5 class="select-payment-text">Payment Choice</h5> -->
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="price" id="inlineRadio1" value="<?=$total_amount?>" checked>
                        <label class="form-check-label" for="inlineRadio1">Pago Total  <span class="payment-price">$<?=(int)$total_amount; ?></span></label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="price" id="inlineRadio2" value="<?=($total_amount/2)?>">
                        <label class="form-check-label" for="inlineRadio2">Pagar la
                            mitad  <span class="payment-price"> $<?= (int)($total_amount/2); ?> </span></label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="price" id="inlineRadio3" value="custom">
                        <label class="form-check-label" for="inlineRadio3">Otro Monto</label>
                    </div>
                    <div class="form-check form-check-inline"style="max-width:15%;margin-top:2px;">
                        <input type="number" name="custom_price" id="custom-price-field" class="form-control" placeholder="Otro monto" min="<?=($total_amount/2)?>" max="<?=$total_amount?>"/>
                    </div>
                </div><!------->
                <div class="border-top div-padding" align="right">
                    <button type="submit" class="btn btn-theme">Pagar</button>

                </div>
                <?php ActiveForm::end(); ?>
            <?php } ?>

        </div>
    </div>
</section>
<script>
    $(document).ready(function() {
        function disablePrev() { window.history.forward() }
        window.onload = disablePrev();
        window.onpageshow = function(evt) { if (evt.persisted) disableBack() }
    });
    var myInput = document.getElementById("custom-price-field");
    myInput.addEventListener("invalid", validate);
    myInput.addEventListener("keyup", validate);

    function validate() {
        var val = parseFloat(this.value);
        var min = parseFloat(this.min);
        var max = parseFloat(this.max);

        if (val < min) {
            this.setCustomValidity('Valor debe ser mayor oigual a ' + min);
        } else if (val > max) {
            this.setCustomValidity('El valor debe ser menor o igual que ' + max);
        } else {
            this.setCustomValidity("");
        }
    }
    $(document).on('click','#inlineRadio3',function (e) {
        $('#custom-price-field').val(0);
    });
    $(document).on('click','#inlineRadio2',function (e) {
        $('#custom-price-field').val('');
    });
    $(document).on('click','#inlineRadio1',function (e) {
        $('#custom-price-field').val('');
    });
</script>
</body>

</html>

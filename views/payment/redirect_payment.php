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
</head>

<body>
<div class="loader"></div>

<form action="<?=$payment_data['url'];?>" method="post" id="webpay-payment-form">
    <input type="hidden" name="token_ws" value="<?=$payment_data['token'];?>">
</form>
<script>
    $('#webpay-payment-form').submit();
</script>
</body>
</html>
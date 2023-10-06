<table class="table table" id="suggestion-table">
    <thead>
    <tr>
        <th scope="col">Tipo</th>
        <th scope="col">Instalacion</th>
        <th scope="col">Check In</th>
        <th scope="col">Check Out</th>
        <th scope="col" class="text-right">Valor</th>
    </tr>
    </thead>
    <tbody>
    <?php
        foreach ($data as $key => $value){
    ?>
    <tr>
        <td id="type-text"><?=$value['ITnombre']?></td>
        <td id="name-text"><?=$value['Inombre']?></td>
        <td id="checkin-text"><?=Yii::$app->formatter->asDate($value['fecha_inicio'])?></td>
        <td id="checkout-text"><?=Yii::$app->formatter->asDate($value['fecha_fin'])?></td>
        <td class="text-right" id="total-amount"><?=$value['valor']?></td>
    </tr>
    <?php } ?>
    </tbody>
</table>
<button type="button" class="btn btn-theme" id="suggestion-confirm-button">Confirmar y Reservar</button>

<script>
    var suggestion_confirm_button = jQuery('#suggestion-confirm-button');
    suggestion_confirm_button.on('click',function(e){
        confirmSuggestion();
        suggestion_confirm_button.hide();
    });
</script>
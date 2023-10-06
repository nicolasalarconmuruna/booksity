<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "reservas_pagos".
 *
 * @property int $id
 * @property int $reservas_id
 * @property string $fecha_pago
 * @property int $formas_pago_id
 * @property string $voucher
 * @property string $voucher_pic
 * @property string $descripcion
 * @property string $valor
 * @property int $created_by
 * @property string $created_on
 *
 * @property Reservas $reservas
 * @property SysFormasPago $formasPago
 */
class ReservasPagos extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'reservas_pagos';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['reservas_id', 'fecha_pago', 'formas_pago_id', 'valor', 'created_by'], 'required'],
            [['reservas_id', 'formas_pago_id', 'created_by'], 'integer'],
            [['fecha_pago', 'created_on'], 'safe'],
            [['valor'], 'number'],
            [['voucher', 'voucher_pic', 'descripcion'], 'string', 'max' => 255],
            [['reservas_id'], 'exist', 'skipOnError' => true, 'targetClass' => Reservas::className(), 'targetAttribute' => ['reservas_id' => 'id']],
            [['formas_pago_id'], 'exist', 'skipOnError' => true, 'targetClass' => SysFormasPago::className(), 'targetAttribute' => ['formas_pago_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'reservas_id' => 'Reservas ID',
            'fecha_pago' => 'Fecha Pago',
            'formas_pago_id' => 'Formas Pago ID',
            'voucher' => 'Voucher',
            'voucher_pic' => 'Voucher Pic',
            'descripcion' => 'Descripcion',
            'valor' => 'Valor',
            'created_by' => 'Created By',
            'created_on' => 'Created On',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReservas()
    {
        return $this->hasOne(Reservas::className(), ['id' => 'reservas_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFormasPago()
    {
        return $this->hasOne(SysFormasPago::className(), ['id' => 'formas_pago_id']);
    }
}

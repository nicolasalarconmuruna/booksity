<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "reservas".
 *
 * @property int $id
 * @property int $instalaciones_id
 * @property string $fecha_inicio
 * @property string $fecha_fin
 * @property string $valor_a_pagar
 * @property string $valor_rack
 * @property string $valor_temporada
 * @property int $status
 * @property int $clientes_id_billing
 * @property string $pay_services
 * @property string $notes
 * @property int $reservas_grupos_id
 * @property int $created_by
 * @property string $created_on
 *
 * @property Instalaciones $instalaciones
 * @property Clientes $clientesIdBilling
 * @property GruposReservas $reservasGrupos
 * @property SysEstadosReservas $status0
 * @property ReservasGuests[] $reservasGuests
 * @property ReservasPagos[] $reservasPagos
 * @property ReservasServicios[] $reservasServicios
 */
class Reservas extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'reservas';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['instalaciones_id', 'fecha_inicio', 'fecha_fin', 'valor_a_pagar', 'status', 'clientes_id_billing', 'pay_services', 'created_by'], 'required'],
            [['instalaciones_id', 'status', 'clientes_id_billing', 'reservas_grupos_id', 'created_by'], 'integer'],
            [['fecha_inicio', 'fecha_fin', 'created_on'], 'safe'],
            [['valor_a_pagar', 'valor_rack', 'valor_temporada'], 'number'],
            [['pay_services', 'notes'], 'string'],
            [['instalaciones_id'], 'exist', 'skipOnError' => true, 'targetClass' => Instalaciones::className(), 'targetAttribute' => ['instalaciones_id' => 'id']],
            [['clientes_id_billing'], 'exist', 'skipOnError' => true, 'targetClass' => Clientes::className(), 'targetAttribute' => ['clientes_id_billing' => 'id']],
            [['reservas_grupos_id'], 'exist', 'skipOnError' => true, 'targetClass' => GruposReservas::className(), 'targetAttribute' => ['reservas_grupos_id' => 'id']],
            [['status'], 'exist', 'skipOnError' => true, 'targetClass' => SysEstadosReservas::className(), 'targetAttribute' => ['status' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'instalaciones_id' => 'Instalaciones ID',
            'fecha_inicio' => 'Fecha Inicio',
            'fecha_fin' => 'Fecha Fin',
            'valor_a_pagar' => 'Valor A Pagar',
            'valor_rack' => 'Valor Rack',
            'valor_temporada' => 'Valor Temporada',
            'status' => 'Status',
            'clientes_id_billing' => 'Clientes Id Billing',
            'pay_services' => 'Pay Services',
            'notes' => 'Notes',
            'reservas_grupos_id' => 'Reservas Grupos ID',
            'created_by' => 'Created By',
            'created_on' => 'Created On',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getInstalaciones()
    {
        return $this->hasOne(Instalaciones::className(), ['id' => 'instalaciones_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getClientesIdBilling()
    {
        return $this->hasOne(Clientes::className(), ['id' => 'clientes_id_billing']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReservasGrupos()
    {
        return $this->hasOne(GruposReservas::className(), ['id' => 'reservas_grupos_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getStatus0()
    {
        return $this->hasOne(SysEstadosReservas::className(), ['id' => 'status']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReservasGuests()
    {
        return $this->hasMany(ReservasGuests::className(), ['reservas_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReservasPagos()
    {
        return $this->hasMany(ReservasPagos::className(), ['reservas_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReservasServicios()
    {
        return $this->hasMany(ReservasServicios::className(), ['reservas_id' => 'id']);
    }
}

<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "clientes".
 *
 * @property int $id
 * @property int $clientes_tipo_id
 * @property string $pasaporte
 * @property string $empresa
 * @property string $nombres
 * @property string $apellidos
 * @property string $genero
 * @property string $telefono
 * @property string $email
 * @property int $email_validated
 * @property string $direccion
 * @property string $comuna
 * @property string $ciudad
 * @property string $pais
 * @property string $cumpleanos
 * @property string $comentarios
 * @property int $created_by
 * @property string $created_on
 *
 * @property SysTipoClientes $clientesTipo
 * @property GruposReservas[] $gruposReservas
 * @property Reservas[] $reservas
 */
class Clientes extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clientes';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['email'], 'required'],
            [['clientes_tipo_id', 'email_validated', 'created_by'], 'integer'],
            [['genero', 'comentarios'], 'string'],
            [['cumpleanos', 'created_on'], 'safe'],
            [['clientes_tipo_id'],'default','value'=>1],
            [['created_by'],'default','value'=>0],
            [['pasaporte', 'empresa', 'nombres', 'apellidos', 'telefono', 'email', 'direccion', 'comuna', 'ciudad', 'pais'], 'string', 'max' => 255],
            [['email'], 'unique'],
            [['pasaporte'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'clientes_tipo_id' => 'Clientes Tipo ID',
            'pasaporte' => 'Pasaporte',
            'empresa' => 'Empresa',
            'nombres' => 'Nombres',
            'apellidos' => 'Apellidos',
            'genero' => 'Genero',
            'telefono' => 'Telefono',
            'email' => 'Email',
            'email_validated' => 'Email Validated',
            'direccion' => 'Direccion',
            'comuna' => 'Comuna',
            'ciudad' => 'Ciudad',
            'pais' => 'Pais',
            'cumpleanos' => 'Cumpleanos',
            'comentarios' => 'Comentarios',
            'created_by' => 'Created By',
            'created_on' => 'Created On',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getClientesTipo()
    {
        return $this->hasOne(SysTipoClientes::className(), ['id' => 'clientes_tipo_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getGruposReservas()
    {
        return $this->hasMany(GruposReservas::className(), ['clientes_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReservas()
    {
        return $this->hasMany(Reservas::className(), ['clientes_id_billing' => 'id']);
    }
}

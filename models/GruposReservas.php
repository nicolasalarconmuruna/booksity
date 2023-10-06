<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "grupos_reservas".
 *
 * @property int $id
 * @property int $clientes_id
 * @property string $descripcion
 * @property int $created_by
 * @property string $created_on
 *
 * @property Clientes $clientes
 * @property Reservas[] $reservas
 */
class GruposReservas extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'grupos_reservas';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['clientes_id'], 'required'],
            [['descripcion'], 'default','value'=>'API de Bookisity generada'],
            [['created_by'], 'default','value'=>2],
            [['created_on'], 'default','value'=>date('Y-m-d H:i:s')],
            [['created_on','created_by'], 'safe'],
            [['descripcion'], 'string', 'max' => 255],
            [['clientes_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clientes::className(), 'targetAttribute' => ['clientes_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'clientes_id' => 'Clientes ID',
            'descripcion' => 'Descripcion',
            'created_by' => 'Created By',
            'created_on' => 'Created On',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getClientes()
    {
        return $this->hasOne(Clientes::className(), ['id' => 'clientes_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReservas()
    {
        return $this->hasMany(Reservas::className(), ['reservas_grupos_id' => 'id']);
    }
}

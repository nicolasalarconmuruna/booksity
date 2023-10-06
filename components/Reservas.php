<?php

namespace app\components;
use yii\base\Component;
use Yii;
class Reservas extends Component
{
    // C R E A T E
    public static function BookRoom($postdata)
    {
        $postdata['instalaciones_id'] = $postdata['room'][0];
        $postdata['fecha_inicio'] = $postdata['room'][1];
        $postdata['fecha_fin'] = $postdata['room'][2];
        $postdata['valor_temporada'] = self::calcula_valor_temporada($postdata['instalaciones_id'], $postdata['fecha_inicio'], $postdata['fecha_fin']);
        $postdata['valor_a_pagar'] = $postdata['valor_temporada'];
        $postdata['valor_rack'] = Instalaciones::get_valor_rack($postdata['instalaciones_id'], $postdata['fecha_inicio'], $postdata['fecha_fin']);
        $query = "INSERT INTO `reservas`(`instalaciones_id`, `fecha_inicio`, `fecha_fin`, `valor_a_pagar`, `valor_rack`, `valor_temporada`, `status`, `clientes_id_billing`, `pay_services`, `notes`, `reservas_grupos_id`, `created_by`, `created_on`) VALUES (:instalaciones_id,:fecha_inicio,:fecha_fin,:valor_a_pagar,:valor_rack,:valor_temporada,:status,:clientes_id_billing,:pay_services,:notes,:reservas_grupos_id,:created_by,:created_on)";

        $result =\Yii::$app->db->createCommand($query)
            ->bindValue(':instalaciones_id' , $postdata['instalaciones_id'] )
            ->bindValue(':fecha_inicio', $postdata['fecha_inicio'] )
            ->bindValue(':fecha_fin', $postdata['fecha_fin'])
            ->bindValue(':valor_a_pagar', $postdata['valor_a_pagar'])
            ->bindValue(':valor_rack', $postdata['valor_rack'])
            ->bindValue(':valor_temporada', $postdata['valor_temporada'])
            ->bindValue(':status', $postdata['status'])
            ->bindValue(':clientes_id_billing', $postdata['clientes_id_billing'])
            ->bindValue(':pay_services', $postdata['pay_services'])
            ->bindValue(':reservas_grupos_id', $postdata['reservas_grupos_id'])
            ->bindValue(':created_by', $postdata['created_by'])
            ->bindValue(':created_on', date('Y-m-d H:i:s'))
            ->bindValue(':notes', $postdata['notes'] )
            ->execute();
        if($result){
            $reservas_id = Yii::$app->db->getLastInsertID();
            $Reservas = \app\models\Reservas::findOne($reservas_id);
            if(!empty($postdata['reservas_grupos_id'])){
                $Reservas->buy_order_id = $postdata['buy_order_prefix'].$postdata['reservas_grupos_id'];
            }else{
                $Reservas->buy_order_id = $postdata['buy_order_prefix'].$reservas_id;
            }
            $Reservas->save(false);
            return $reservas_id;
        }
        return false;
    }
    // I N S E R T
    public static function before_insert($postdata, $reservas)
    {
        $instalacion = $postdata->get('instalaciones_id');
        $fecha_inicio = $postdata->get('fecha_inicio');
        $fecha_fin = $postdata->get('fecha_fin');
        $valor_a_pagar = $postdata->get('valor_a_pagar');

        if (
            //self::check_before_today($fecha_fin, $reservas)
            self::check_fechas($fecha_inicio, $fecha_fin, $reservas)
            //&& self::check_overlap_reservas($instalacion, $fecha_inicio, $fecha_fin, $reservas)
            //&& self::check_if_mantenciones($instalacion, $fecha_inicio, $fecha_fin, $reservas)
            && Instalaciones::is_available($fecha_inicio, $fecha_fin, $instalacion, false, false, false, $reservas)
        )
        {
            if ($valor_rack = Instalaciones::get_valor_rack($instalacion, $fecha_inicio, $fecha_fin))
                $postdata->set('valor_rack', $valor_rack);

            if ($calculated_valor_a_pagar = self::calcula_valor_temporada($instalacion, $fecha_inicio, $fecha_fin))
                $postdata->set('valor_temporada', $calculated_valor_a_pagar);

            if ($valor_a_pagar == NULL || $valor_a_pagar == 0)
                $postdata->set('valor_a_pagar', $calculated_valor_a_pagar);
        }
    }
    public static function after_insert($postdata, $primary, $reservas)
    {
        $msg_status = 'success';
        $clientes_id_billing = $postdata->get('clientes_id_billing');
        $status = $postdata->get('status');
        $send_email = $postdata->get('send_email');

        Huespedes::add_new_guest($primary, $clientes_id_billing);

        if (($status == 1 && $send_email) || ($status == 2))
            if(!(self::send_email_newbooking($postdata, $primary)))
            {
                $msg_email = ', pero no se pudo enviar el email.';
                $msg_status = 'note';
            }

        Log::LogOnInsert($postdata, $primary, $reservas);

        $reservas->set_message('La reserva ha sido creada con éxito!'.@$msg_email,$msg_status);
    }

    // U P D A T E
    public static function before_update($postdata, $primary, $reservas)
    {
        $user_type = $reservas->get_var('user_type');
        $instalacion = $postdata->get('instalaciones_id');
        $fecha_inicio = $postdata->get('fecha_inicio');
        $fecha_fin = $postdata->get('fecha_fin');
        $valor_a_pagar = $postdata->get('valor_a_pagar');
        $status = $postdata->get('status');
        $clientes_id_billing = $postdata->get('clientes_id_billing');
        $old_values = self::get_data($primary);

        if (
            ($user_type == 'user' &&
                //self::check_before_today($old_values['fecha_fin'], $reservas)  				// reserva del pasado
                self::check_status_checkout($old_values['status'], $reservas)				// en estado check out
                && self::check_cambios_before_today($postdata, $old_values, $reservas)  // fecha inicio del pasado
                && self::check_fechas($fecha_inicio, $fecha_fin, $reservas)
                && Instalaciones::is_available($fecha_inicio, $fecha_fin, $instalacion,false,false,false,$reservas, $primary)
            )
            ||
            ($user_type != 'user'
                && Instalaciones::is_available($fecha_inicio, $fecha_fin, $instalacion,false,false,false,$reservas, $primary)
            )
        )
        {

            if ($valor_rack = Instalaciones::get_valor_rack($instalacion, $fecha_inicio, $fecha_fin))
                $postdata->set('valor_rack', $valor_rack);

            if ($calculated_valor_a_pagar = self::calcula_valor_temporada($instalacion, $fecha_inicio, $fecha_fin))
                $postdata->set('valor_temporada', $calculated_valor_a_pagar);

            if ($valor_a_pagar == NULL || $valor_a_pagar == 0 || ((($fecha_inicio != $old_values['fecha_inicio']) || ($fecha_fin != $old_values['fecha_fin'])) && ($valor_a_pagar == $old_values['valor_a_pagar'])))
                $postdata->set('valor_a_pagar', $calculated_valor_a_pagar);

            if ($clientes_id_billing != $old_values['clientes_id_billing'])
            {
                Huespedes::add_new_guest($primary, $clientes_id_billing);
                Huespedes::remove_old_guest($primary, $old_values['clientes_id_billing']);
            }

            if($old_values['status'] < 2 && $status == 2)
                self::send_email_newbooking($postdata, $primary);

            if($old_values['status'] != 4 && $status == 4)
                self::send_email_qos($clientes_id_billing, $primary);
        }
    }
    public static function after_update($postdata, $primary, $reservas)
    {
        Log::LogOnUpdate($postdata, $primary, $reservas);

        $reservas->set_message('La reserva ha sido acutalizada con éxito!','success');
    }
    public static function updateStatus($reserva_id, $status_deseado)
    {
        $status_posibles = array('1','2','3','4','5');
        if(!self::get_data($reserva_id) || !in_array($status_deseado, $status_posibles)) {
            return false;
        }

        $db = Xcrud_db::get_instance();
        $query = "UPDATE reservas SET status = ".$db->escape($status_deseado)." WHERE id = ".$db->escape($reserva_id);
        $num_rows = $db->query($query);

        return ($num_rows > 0) ? true : false ;
    }

    // R E M O V E
    public static function before_remove($primary, $reservas)
    {
        $old_values = self::get_data($primary);
        $user_type = $reservas->get_var('user_type');

        //if($user_type != 'superadmin' && $user_type != 'admin')
        //self::check_before_today($old_values['fecha_inicio'], $reservas);

        self::check_status_checkout($old_values['status'], $reservas);
        self::check_has_payments($primary, $reservas);
    }
    public static function after_remove($primary, $reservas)
    {
        Log::LogOnDelete($primary, $reservas);

        $reservas->set_message('La reserva ha sido eliminada.','success');
    }

    // O T H E R   P U B L I C   M E T H O D S
    public static function add_status_color($value, $fieldname, $primary_key, $row, $reservas)
    {
        //error_log($row[$fieldname]); //error_log($xcrud["hidden_fields"]["task"]);
        switch ($row[$fieldname]) {
            case 0:
                return '<span class="label bg-red xcrud-tooltip" title="Reserva excedió el tiempo de bloqueo."> Pendiente </span>';
                break;
            case 1:
                return '<span class="label bg-yellow"> Pendiente </span>';
                break;
            case 2:
                return '<span class="label bg-blue"> Confirmada </span>';
                break;
            case 3:
                return '<span class="label bg-purple"> Check In </span>';
                break;
            case 4:
                return '<span class="label bg-grey-cascade"> Check Out </span>';
                break;
            case 5:
                return '<span class="label bg-grey-cascade"> Anulada </span>';
                break;
            default:
                return '<span> ' . $value . ' </span>';
                break;
        }
    }
    public static function checkin($xcrud)
    {
        if ($xcrud->get('primary') !== false)
        {
            $primary = (int)$xcrud->get('primary');
            $db = Xcrud_db::get_instance();
            $query = "UPDATE `reservas` SET `status` = '3' WHERE id = ".$primary;
            $rows_affected = $db->query($query);

            if ($rows_affected > 0)
                $xcrud->set_message('Se actualizó el estado de la reserva!','success');
        }
        else
            $xcrud->set_message('No se pudo actualizar el estado de la reserva','error');
    }
    public static function checkout($xcrud)
    {
        if ($xcrud->get('primary') !== false)
        {
            $primary = (int)$xcrud->get('primary');
            $db = Xcrud_db::get_instance();
            $query = "UPDATE `reservas` SET `status` = '4' WHERE id = ".$primary;
            $rows_affected = $db->query($query);

            if ($rows_affected > 0)
                $xcrud->set_message('Se actualizó el estado de la reserva!','success');

            self::send_email_qos($xcrud->get('customer'), $xcrud->get('primary'));
        }
        else
            $xcrud->set_message('No se pudo actualizar el estado de la reserva','error');
    }
    public static function calcula_valor_temporada($instalacion, $fecha_inicio, $fecha_fin)
    {
        $id = uniqid('ccg_');
        $query = "SELECT `fn_Calcular_Valor_a_Cobrar`(:id, :fecha_inicio, :fecha_fin, :instalacion) AS `fn_Calcular_Valor_a_Cobrar`";
        $row =\Yii::$app->db->createCommand($query)
            ->bindValue(':id' , $id )
            ->bindValue(':fecha_inicio', $fecha_inicio)
            ->bindValue(':fecha_fin', $fecha_fin)
            ->bindValue(':instalacion', $instalacion)
            ->queryOne();
        if($row['fn_Calcular_Valor_a_Cobrar'] > 0)
            return $row['fn_Calcular_Valor_a_Cobrar'];
        else
            return false;
    }
    public static function get_bookings_between($fecha_inicio, $fecha_fin, $instalacion = false)
    {
        $specific_instalacion = (is_array($instalacion)) ? ' WHERE I.id IN '.$instalacion : ($instalacion != false) ? ' WHERE I.id = '.$db->escape($instalacion) : '';

        $db = Xcrud_db::get_instance();
        $query = "
					SELECT 
						I.nombre,
						R.id, R.fecha_inicio, R.fecha_fin, R.instalaciones_id, R.status, R.clientes_id_billing, R.created_on
					FROM instalaciones I
					LEFT JOIN instalaciones_tipo IT ON (IT.id = I.tipo_instalacion_id)
					LEFT JOIN reservas R ON (I.id = R.instalaciones_id AND (R.status != 5 AND ((R.fecha_inicio >= '$fecha_inicio' AND R.fecha_inicio <= '$fecha_fin') OR (R.fecha_fin > '$fecha_inicio' AND R.fecha_fin <= '$fecha_fin') OR (R.fecha_inicio < '$fecha_inicio' AND R.fecha_fin > '$fecha_fin'))))
					".$specific_instalacion."
					ORDER BY I.nombre, R.fecha_inicio
					";

        $db->query($query);
        $results_bookings = $db->result();
        $num_rows = count($results_bookings);

        if ($num_rows > 0)
            return $results_bookings;
        else
            return false;
    }
    public static function check_fechas($fecha_inicio, $fecha_fin, $reservas)
    {
        if ($fecha_inicio >= $fecha_fin)
        {
            $reservas->set_exception('fecha_fin','Fecha de Check Out debe ser posterior que la fecha de Check In','error');
            return false;
        }
        else
            return true;
    }
    public static function get_data($primary)
    {
        $db = Xcrud_db::get_instance();
        $query = "SELECT * FROM reservas WHERE id = ".$db->escape($primary);
        $db->query($query);
        $row = $db->row();
        $num_rows = count($row);

        return ($num_rows > 0) ? $row : false ;
    }
    public static function enviar_email_confirmacion($postdata)
    {
        $primary = array($postdata['id']);
        $postdata['room'][0][0] = $postdata['instalaciones_id'];
        $postdata['room'][0][1] = $postdata['fecha_inicio'];
        $postdata['room'][0][2] = $postdata['fecha_fin'];
        $postdata['room'][0][3] = $postdata['valor_a_pagar'];

        return (self::send_email_newbooking($postdata, $primary, true)) ? true : false ;
    }


    ////////// P R I V A T E   M E T H O D S //////////

    private static function check_cambios_before_today($postdata, $old_values, $reservas)
    {
        $today = date("Y-m-d");

        if (($old_values['fecha_inicio'] < $today)
            && (
                ($postdata->get('fecha_inicio') != $old_values['fecha_inicio'])
                || ($postdata->get('instalaciones_id') != $old_values['instalaciones_id'])
                || ($postdata->get('clientes_id_billing') != $old_values['clientes_id_billing'])
            ))
        {
            $reservas->set_exception('fecha_inicio,instalaciones_id,clientes_id_billing','Los campos indicados no se pueden modificar para una reserva en curso.','error');
            return false;
        }
        else
            return true;
    }
    private static function check_status_checkout($status, $reservas)
    {
        $status_no_modificables = array(4,5);
        if (in_array($status, $status_no_modificables))
        {
            $reservas->set_exception('status','No se puede modificar una reserva en el estado actual.','error');
            return false;
        }
        else
            return true;
    }
    private static function check_has_payments($primary, $reservas)
    {
        if(!class_exists('Pagos')) require BKY_DOCROOT_PATH.'classes/reservas_pagos.php';

        if(Pagos::booking_payments($primary))
        {
            $reservas->set_exception('instalaciones_id','Revise los pagos asociados y elimínelos antes de eliminar la reserva.','note');
            return false;
        }
        else
            return true;
    }
    private static function send_email_qos($customer_id, $primary)
    {
        if (Config::Get('booking.email_checkout'))
        {
            $cliente = Clientes::get_data($customer_id);

            $content = str_replace("{nombre_cliente}", $cliente["nombres"], Config::Get("custom.qospoll_message"));
            $content = str_replace("{nombre_hotel}", Config::Get("hotel.name"), $content);
            $content = str_replace("{link}", Config::Get("custom.qospoll_link"), $content);
            $content = str_replace("{id_reserva}", $primary, $content);

            $message = file_get_contents(BKY_DOCROOT_PATH.Config::Get("email.template"));
            $message = str_replace("{website_url}", Config::Get("hotel.website_url"), $message);
            $message = str_replace("{logo}", Config::Get("hotel.logo"), $message);
            $message = str_replace("{slogan}", Config::Get("hotel.slogan"), $message);
            $message = str_replace("{nombre_hotel}", Config::Get("hotel.name"), $message);
            $message = str_replace("{content}", $content, $message);

            Email::SendEmail($cliente['email'], Config::Get("custom.qospoll_subject"), $message, false);
        }
    }
    private static function send_email_newbooking($postdata, $primary, $not_xcrud=false)
    {
        $customer_id = ($not_xcrud) ? $postdata['clientes_id_billing'] : $postdata->get('clientes_id_billing') ;
        $status = ($not_xcrud) ? $postdata['status'] : $postdata->get('status') ;
        $cliente = Clientes::get_data($customer_id);

        if($not_xcrud) {
            $primary_string = $registros = $valor_total = '';

            foreach ($primary as $key => $value)
                $primary_string .= '#'.$value.', ';
            $primary = rtrim($primary_string, ', ');

            foreach ($postdata['room'] as $key => $value) {
                $instalaciones_id = $postdata['room'][$key][0];
                $fecha_inicio = $postdata['room'][$key][1];
                $fecha_fin = $postdata['room'][$key][2];
                $valor_a_pagar = $postdata['room'][$key][3];
                $instalacion = Instalaciones::get_data($instalaciones_id);
                $diff = abs(strtotime($fecha_fin) - strtotime($fecha_inicio));
                $nights = floor($diff / (60*60*24));

                $registro = str_replace("{instalacion}", $instalacion['ITnombre'], Config::Get("custom.email_booking_record"));
                $registro = str_replace("{checkin}", $fecha_inicio, $registro);
                $registro = str_replace("{checkout}", $fecha_fin, $registro);
                $registro = str_replace("{noches}", $nights, $registro);
                $registro = str_replace("{total}", "$ ".number_format($valor_a_pagar,0,',','.'), $registro);

                $registros = $registros . $registro;
                $valor_total = $valor_total + $valor_a_pagar;
            }
            if(count($postdata['room']) > 1) {
                $registro = str_replace("{instalacion}", '', Config::Get("custom.email_booking_record"));
                $registro = str_replace("{checkin}", '', $registro);
                $registro = str_replace("{checkout}", '', $registro);
                $registro = str_replace("{noches}", '<b>TOTAL</b>', $registro);
                $registro = str_replace("{total}", "<b>$ ".number_format($valor_total,0,',','.')."</b>", $registro);
                $registros = $registros . $registro;
            }
        }
        else {
            $primary = '#'.$primary;
            $instalaciones_id = $postdata->get('instalaciones_id') ;
            $fecha_inicio = $postdata->get('fecha_inicio') ;
            $fecha_fin = $postdata->get('fecha_fin') ;
            $valor_a_pagar = $postdata->get('valor_a_pagar');
            $instalacion = Instalaciones::get_data($instalaciones_id);
            $diff = abs(strtotime($fecha_fin) - strtotime($fecha_inicio));
            $nights = floor($diff / (60*60*24));

            $registros = str_replace("{instalacion}", $instalacion['ITnombre'], Config::Get("custom.email_booking_record"));
            $registros = str_replace("{checkin}", $fecha_inicio, $registros);
            $registros = str_replace("{checkout}", $fecha_fin, $registros);
            $registros = str_replace("{noches}", $nights, $registros);
            $registros = str_replace("{total}", "$ ".number_format($valor_a_pagar,0,',','.'), $registros);
        }

        if ($status == 1 && Config::Get('booking.email_new'))
        {
            $content = str_replace("{nombre_cliente}", $cliente["nombres"], Config::Get("custom.newbooking_message"));
            $content = str_replace("{horas}", Config::Get("booking.blocking_time"), $content);
            $subject = Config::Get("custom.newbooking_subject");
        }
        else if ($status == 2 && Config::Get('booking.email_confirmed'))
        {
            $content = str_replace("{nombre_cliente}", $cliente["nombres"], Config::Get("custom.confirmedbooking_message"));
            $subject = Config::Get("custom.confirmedbooking_subject");
        }
        else
            return false;

        $content = str_replace("{nombre_hotel}", Config::Get("hotel.name"), $content);
        $content = str_replace("{id_reserva}", $primary, $content);
        $content = str_replace("{fono}", Config::Get("hotel.fono"), $content);
        $content = str_replace("{email}", Config::Get("email.from"), $content);
        $content = str_replace("{registros}", $registros, $content);

        $message = file_get_contents(BKY_DOCROOT_PATH.Config::Get("email.template"));
        $message = str_replace("{website_url}", Config::Get("hotel.website_url"), $message);
        $message = str_replace("{logo}", Config::Get("hotel.logo"), $message);
        $message = str_replace("{slogan}", Config::Get("hotel.slogan"), $message);
        $message = str_replace("{nombre_hotel}", Config::Get("hotel.name"), $message);
        $message = str_replace("{content}", $content, $message);

        $envio = Email::SendEmail($cliente['email'], $subject, $message, false);
        return ($envio) ? true : false;
    }
}
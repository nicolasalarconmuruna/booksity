<?php

namespace app\components;
use yii\base\Component;
class Instalaciones extends Component
{
    // I N S E R T
    public static function before_insert($postdata, $instalaciones)
    {
    }
    public static function after_insert($postdata, $primary, $instalaciones)
    {
        Log::LogOnInsert($postdata, $primary, $instalaciones);
    }

    // U P D A T E
    public static function before_update($postdata, $primary, $instalaciones)
    {
    }
    public static function after_update($postdata, $primary, $instalaciones)
    {
        Log::LogOnUpdate($postdata, $primary, $instalaciones);
    }

    // R E M O V E
    public static function before_remove($primary, $instalaciones)
    {
    }
    public static function after_remove($primary, $instalaciones)
    {
        Log::LogOnDelete($primary, $instalaciones);
    }


    ////////// O T H E R   P U B L I C   M E T H O D S //////////
    public static function activar_action($xcrud)
    {
        if ($xcrud->get('primary'))
        {
            $db = Xcrud_db::get_instance();
            $query = 'UPDATE instalaciones SET `status` = \'1\' WHERE id = ' . (int)$xcrud->get('primary');
            $db->query($query);
        }
    }
    public static function desactivar_action($xcrud)
    {
        if ($xcrud->get('primary'))
        {
            $db = Xcrud_db::get_instance();
            $query = 'UPDATE instalaciones SET `status` = \'0\' WHERE id = ' . (int)$xcrud->get('primary');
            $db->query($query);
        }
    }
    public static function activar_online_action($xcrud)
    {
        if ($xcrud->get('primary'))
        {
            $db = Xcrud_db::get_instance();
            $query = 'UPDATE instalaciones SET `online` = \'1\' WHERE id = ' . (int)$xcrud->get('primary');
            $db->query($query);
        }
    }
    public static function desactivar_online_action($xcrud)
    {
        if ($xcrud->get('primary'))
        {
            $db = Xcrud_db::get_instance();
            $query = 'UPDATE instalaciones SET `online` = \'0\' WHERE id = ' . (int)$xcrud->get('primary');
            $db->query($query);
        }
    }
    public static function get_data($id)
    {
        $query = "SELECT I.id as Iid, I.nombre as Inombre, IT.nombre as ITnombre, IT.descripcion as ITdescripcion
				FROM instalaciones I
				LEFT JOIN instalaciones_tipo IT ON I.tipo_instalacion_id = IT.id
				WHERE I.id = ".$id;

        $row = \Yii::$app->db->createCommand($query)->queryOne();
        $row_count = count($row);

        if ($row_count > 0)
            return $row;
        else
            return false;
    }
    public static function get_all_facilities()
    {
        $db = Xcrud_db::get_instance();
        $query = "SELECT *
				FROM instalaciones
				ORDER BY nombre
				";

        $db->query($query);
        $results = $db->result();
        $row_count = count($results);

        if ($row_count > 0)
            return $results;
        else
            return false;
    }
    public static function is_available($fecha_inicio, $fecha_fin, $id_instalacion = false, $tipo = false, $limit = false, $channel = false, $xcrud = false, $id_reserva = false)
    {
        $channel = ($channel != false && $channel == 'online') ? ' AND I.online = 1 ' : '' ;
        $tipo = ($tipo != false) ? ' AND IT.id = '.(int)$tipo : '' ;
        $limit = ($limit != false) ? ' LIMIT '.(int)$limit : '' ;
        $id_instalacion = (is_array($id_instalacion)) ? ' AND I.id in ('.$id_instalacion.') ' : ($id_instalacion != false) ? ' AND I.id = '.$id_instalacion.' ' : '';
        $id_reserva = ($id_reserva != false) ? ' R.id != '.$id_reserva.' AND ' : '';

        $query = "
					SELECT 
						I.id
					FROM instalaciones I
					LEFT JOIN instalaciones_tipo IT ON (IT.id = I.tipo_instalacion_id )
					LEFT JOIN reservas R ON (I.id = R.instalaciones_id AND ".$id_reserva." ((R.fecha_inicio >= '$fecha_inicio' AND R.fecha_inicio < '$fecha_fin') OR (R.fecha_fin > '$fecha_inicio' AND R.fecha_fin <= '$fecha_fin') OR (R.fecha_inicio < '$fecha_inicio' AND R.fecha_fin > '$fecha_fin')) AND (R.status in (2,3,4) || (R.status = 1 AND R.created_on > (NOW() - INTERVAL 24 HOUR))))
					LEFT JOIN mantenciones M ON (I.id = M.instalaciones_id AND ((M.start_date >= '$fecha_inicio' AND M.start_date <= '$fecha_fin') OR (M.end_date >= '$fecha_inicio' AND M.end_date < '$fecha_fin') OR (M.start_date < '$fecha_inicio' AND M.end_date > '$fecha_fin')))
					LEFT JOIN reservas RO ON (I.id = RO.instalaciones_id)
					WHERE 
						I.status = 1 "
            .$tipo
            .$channel
            .$id_instalacion
            ."
						AND R.instalaciones_id IS NULL
						AND M.instalaciones_id IS NULL
					GROUP BY I.id
					ORDER BY I.tipo_instalacion_id, IFNULL(SUM(DATEDIFF(RO.fecha_fin, RO.fecha_inicio)),0) asc
					".$limit;

        $row_results = \Yii::$app->db->createCommand($query)->queryAll();
        $row_count = count($row_results);

        if ($row_count > 0)
            return $row_results;
        else
        {
            return false;
        }
    }
    public static function get_valor_rack($instalacion, $fecha_inicio, $fecha_fin)
    {
        $query = "
					SELECT 
					instalaciones_tipo.valor_rack
					FROM  instalaciones
					LEFT JOIN instalaciones_tipo ON instalaciones.tipo_instalacion_id = instalaciones_tipo.id
					WHERE instalaciones.id = ".$instalacion;
        $row = \Yii::$app->db->createCommand($query)->queryOne();

        $dStart = new \DateTime($fecha_inicio);
        $dEnd  = new \DateTime($fecha_fin);
        $dDiff = $dStart->diff($dEnd)->days;

        $valor_rack = $dDiff * $row['valor_rack'];
        if ($valor_rack > 0)
            return $valor_rack;
        else
            return false;
    }
    public static function GetExtremesUsage($order = 'asc', $limit = '5')
    {
        $db = Xcrud_db::get_instance();
        $query = "
					SELECT 
						I.id as I_id,
						I.nombre as I_nombre,
						IT.nombre as IT_nombre,
						IFNULL(SUM(DATEDIFF(fecha_fin, fecha_inicio)),0) as dias
					FROM  instalaciones I
					LEFT JOIN instalaciones_tipo IT ON I.tipo_instalacion_id = IT.id
					LEFT JOIN reservas R ON R.instalaciones_id = I.id
					GROUP BY I.id, I.nombre, IT.nombre
					ORDER BY dias $order
					LIMIT 0,$limit
					";

        $db->query($query);
        $results = $db->result();
        $row_count = count($results);

        if ($row_count > 0)
            return $results;
        else
            return false;
    }
    public static function is_combination_suggestion_available($fecha_inicio, $fecha_fin, $channel = false, $tipo = false){
        $i = 0;
        $next_end_date = $fecha_inicio;
        while ($next_end_date < $fecha_fin)
        {
            if($option[$i] = Instalaciones::longest_option_available($next_end_date, $fecha_fin,false,$tipo))
            {
                $room = Instalaciones::get_data($option[$i]['id']);
                $option[$i]['Iid'] = $room['Iid'];
                $option[$i]['Inombre'] = $room['Inombre'];
                $option[$i]['ITnombre'] = $room['ITnombre'];

                if($valor_a_cobrar = Reservas::calcula_valor_temporada($option[$i]['id'], $option[$i]['fecha_inicio'], $option[$i]['fecha_fin']))
                    $option[$i]['valor'] = number_format($valor_a_cobrar,0,',','.');
                else
                    $option[$i]['valor'] = 'Valor desconocido';
            }
            else
                break;

            $next_end_date = $option[$i]['fecha_fin'];
            $i++;
        }
        if($next_end_date == $fecha_fin){
            return ['status'=>1,'data'=>$option];
        }
        return ['status'=>0,'data'=>[]];
    }
    public static function longest_option_available($fecha_inicio, $fecha_fin, $channel = false, $tipo = false)
    {
        $next_day = date('Y-m-d', strtotime($fecha_inicio . ' + 1 day'));
        $dates = array();

        if($rooms = self::is_available($fecha_inicio, $next_day, false, $tipo, false, $channel))
        {
            foreach($rooms as $room)
            {
                if($next_node = self::next_booking($room['id'], $fecha_inicio))
                    $dates[$room['id']] = $next_node['fecha_inicio'];
                else
                    $dates[$room['id']] = $fecha_fin;

                $options[$room['id']] = array(
                    'id' => $room['id'],
                    'fecha_inicio' => $fecha_inicio,
                    'fecha_fin' => $dates[$room['id']],
                );
            }

            uasort($dates,function ($date1, $date2){
                $date1 = date_create($date1);
                $date2 = date_create($date2);
                return ($date1 == $date2) ? 0 : ($date1 < $date2) ? 1 : -1 ;
            });
            $best_option = $options[key($dates)];

            return $best_option;
        }
        else
            return false;
    }
    public static function get_tipos()
    {
        $db = Xcrud_db::get_instance();
        $query = "
					SELECT 
						IT.id as ITid,
						IT.nombre as ITnombre, 
						IT.max_guests as ITmax_guests,
						IT.descripcion as ITdescripcion
					FROM  instalaciones_tipo IT
					";

        $db->query($query);
        $results = $db->result();
        $row_count = count($results);

        return ($row_count > 0) ? $results : false ;
    }

    ////////// P R I V A T E   M E T H O D S //////////
    private static function next_booking($instalaciones_id, $fecha_fin)
    {
        $query = "SELECT 
						id,
						fecha_inicio,
						fecha_fin
					FROM reservas
					WHERE 
						instalaciones_id = '$instalaciones_id'
						AND fecha_inicio >= '$fecha_fin'
						AND (status in (2,3,4) || (status = 1 AND created_on > (NOW() - INTERVAL 24 HOUR)))
					ORDER BY fecha_inicio ASC
					LIMIT 1
					";
        $results = \Yii::$app->db->createCommand($query)->queryOne();
        //$row_count = count($results);
        //echo "<pre>";print_r($results);exit;
        if ($results)
            return $results;
        else
            return false;
    }
}
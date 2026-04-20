<?php

namespace App\CoreFacturalo\Requests\Api\Transform;

use App\CoreFacturalo\Requests\Api\Transform\Common\EstablishmentTransform;
use App\CoreFacturalo\Requests\Api\Transform\Common\PersonTransform;
use App\CoreFacturalo\Requests\Api\Transform\Common\LegendTransform;
use App\CoreFacturalo\Requests\Api\Transform\Common\ActionTransform;
use Modules\Dispatch\Models\DispatchAddress;
use App\Models\Tenant\PersonAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchTransform
{
    public static function transform($inputs)
    {
        $data = [
            'id' => null,
            'series' => Functions::valueKeyInArray($inputs, 'serie_documento'),
            'number' => Functions::valueKeyInArray($inputs, 'numero_documento'),
            'date_of_issue' => Functions::valueKeyInArray($inputs, 'fecha_de_emision'),
            'time_of_issue' => Functions::valueKeyInArray($inputs, 'hora_de_emision'),
            'document_type_id' => Functions::valueKeyInArray($inputs, 'codigo_tipo_documento'),
            'establishment' => EstablishmentTransform::transform($inputs['datos_del_emisor']),
            'customer' => self::customer($inputs),
            'observations' => Functions::valueKeyInArray($inputs, 'observaciones'),
            'transport_mode_type_id' => Functions::valueKeyInArray($inputs, 'codigo_modo_transporte'),
            'transfer_reason_type_id' => Functions::valueKeyInArray($inputs, 'codigo_motivo_traslado'),
            'transfer_reason_description' => Functions::valueKeyInArray($inputs, 'descripcion_motivo_traslado'),
            'date_of_shipping' => Functions::valueKeyInArray($inputs, 'fecha_de_traslado'),
            'transshipment_indicator' => Functions::valueKeyInArray($inputs, 'indicador_de_transbordo'),
            'port_code' => Functions::valueKeyInArray($inputs, 'codigo_de_puerto'),
            'unit_type_id' => Functions::valueKeyInArray($inputs, 'unidad_peso_total'),
            'total_weight' => Functions::valueKeyInArray($inputs, 'peso_total'),
            'packages_number' => Functions::valueKeyInArray($inputs, 'numero_de_bultos'),
            'container_number' => Functions::valueKeyInArray($inputs, 'numero_de_contenedor'),
//            'license_plate' => Functions::valueKeyInArray($inputs, 'numero_de_placa'),
            'origin' => self::origin($inputs),
            'delivery' => self::delivery($inputs),
            'dispatcher' => self::dispatcher($inputs),
            'driver' => self::driver($inputs),
            'transport' => self::transport($inputs),
            'items' => self::items($inputs),
            'legends' => LegendTransform::transform($inputs),
            'actions' => ActionTransform::transform($inputs),
            'additional_data' => Functions::valueKeyInArray($inputs, 'dato_adicional'),
            //dispatchCarrier
            'supplier_addresses' => Functions::valueKeyInArray($inputs, 'direcciones_proveedores'),
            'sender_id' => Functions::valueKeyInArray($inputs, 'remitente_id'),
            'sender_data' => self::senderData($inputs),
            'sender_address_id' => Functions::valueKeyInArray($inputs, 'direccion_remitente_id'),
            'sender_address_data' => self::addressData($inputs, 'sender'),
            'receiver_id' => Functions::valueKeyInArray($inputs, 'destinatario_id'),
            'receiver_data' => self::receiverData($inputs),
            'receiver_address_id' => Functions::valueKeyInArray($inputs, 'direccion_destinatario_id'),
            'receiver_address_data' => self::addressData($inputs, 'receiver'),
            'date_delivery_to_transport' => Functions::valueKeyInArray($inputs, 'fecha_entrega_transporte'),
            'secondary_transports' => self::secondary_transports($inputs),
            'secondary_drivers' => self::secondary_drivers($inputs),
            'payer' => self::payer($inputs),
            'reference_documents' => self::documentRelated($inputs),
        ];
        self::AffectedDocument($data, $inputs);
        return $data;
    }
    private static function addressData($inputs, $type)
    {
        if (key_exists('direccion_remitente_id', $inputs) ||
            key_exists('direccion_destinatario_id', $inputs)) {
            $key = ($type === 'sender') ? 'direccion_remitente_id' : 'direccion_destinatario_id';
            $dispatchAddress = PersonAddress::find($inputs[$key]);
            $location_id = [$dispatchAddress->department_id,$dispatchAddress->province_id,$dispatchAddress->district_id];
            return [
                'address' => $dispatchAddress->address,
                'location_id' => $location_id,
            ];
        } else if (key_exists('direcciones_proveedores', $inputs)) {
            $key = ($type === 'sender') ? 'remitente' : 'destinatario';
            $data = $inputs['direcciones_proveedores'][$key];

            $locations = DB::connection('tenant')->table('districts')
            ->join('provinces', 'districts.province_id', '=', 'provinces.id')
            ->join('departments', 'provinces.department_id', '=', 'departments.id')
            ->where('districts.id', '=', $data['ubigeo'])
            ->select('districts.id as district_id', 'provinces.id as province_id','departments.id as department_id')
            ->first();
            
            if ($locations) {
                $locations = (array) $locations;
            }
            
            $location_id = [ $locations['department_id'], $locations['province_id'],$locations['district_id']];
            return [
                'address' => $data["direccion"],
                'location_id' =>  $location_id
            ];
        }
        return null;

    }

    private static function senderData($inputs)
    {
        if (key_exists('datos_remitente', $inputs)) {
            $sender_data = $inputs['datos_remitente'];

            return [
                'identity_document_type_id' => $sender_data['codigo_tipo_documento_identidad'],
                'identity_document_type_description' => $sender_data['descripcion_tipo_documento_identidad'],
                'number' => $sender_data['numero_documento'],
                'name' => $sender_data['apellidos_y_nombres_o_razon_social'],
            ];
        }
        return null;
    }

    private static function receiverData($inputs)
    {
        if (key_exists('datos_destinatario', $inputs)) {
            $receiver_data = $inputs['datos_destinatario'];

            return [
                'identity_document_type_id' => $receiver_data['codigo_tipo_documento_identidad'],
                'identity_document_type_description' => $receiver_data['descripcion_tipo_documento_identidad'],
                'number' => $receiver_data['numero_documento'],
                'name' => $receiver_data['apellidos_y_nombres_o_razon_social'],
            ];
        }
        return null;
    }

    private static function customer($inputs)
    {
        if (key_exists('datos_del_cliente_o_receptor', $inputs)) {
            return PersonTransform::transform($inputs['datos_del_cliente_o_receptor']);
        }

        return null;
    }

    private static function AffectedDocument(&$data, $inputs)
    {
        if (isset($inputs['documento_afectado']) && isset($inputs['documento_afectado']['external_id'])) {
            $data['affected_document_external_id'] = Functions::valueKeyInArray($inputs['documento_afectado'],
                'external_id');
        } elseif (isset($inputs['documento_afectado'])) {
            $data['data_affected_document']['number'] = Functions::valueKeyInArray($inputs['documento_afectado'],
                'numero_documento');
            $data['data_affected_document']['series'] = Functions::valueKeyInArray($inputs['documento_afectado'],
                'serie_documento');
            $data['data_affected_document']['document_type_id'] = Functions::valueKeyInArray($inputs['documento_afectado'],
                'codigo_tipo_documento');
        }
    }

    private static function origin($inputs)
    {
        if (key_exists('direccion_partida', $inputs)) {
            $origin = $inputs['direccion_partida'];

            return [
                'location_id' => $origin['ubigeo'],
                'address' => $origin['direccion'],
                'code' => $origin['codigo_del_domicilio_fiscal'],
            ];
        }
        return null;
    }

    private static function delivery($inputs)
    {
        if (key_exists('direccion_llegada', $inputs)) {
            $delivery = $inputs['direccion_llegada'];

            return [
                'location_id' => $delivery['ubigeo'],
                'address' => $delivery['direccion'],
                'code' => $delivery['codigo_del_domicilio_fiscal'],
            ];
        }
        return null;
    }

    private static function dispatcher($inputs)
    {
        if (key_exists('transportista', $inputs)) {
            $dispatcher = $inputs['transportista'];

            return [
                'identity_document_type_id' => $dispatcher['codigo_tipo_documento_identidad'],
                'number' => $dispatcher['numero_documento'],
                'name' => $dispatcher['apellidos_y_nombres_o_razon_social'],
                'number_mtc' => $dispatcher['numero_mtc'],
            ];
        }
        return null;
    }

    private static function driver($inputs)
    {
        if (key_exists('chofer', $inputs)) {
            $driver = $inputs['chofer'];
            return [
                'identity_document_type_id' => $driver['codigo_tipo_documento_identidad'],
                'number' => $driver['numero_documento'],
                'name' => Functions::valueKeyInArray($driver, 'nombres'),
                'license' => Functions::valueKeyInArray($driver, 'numero_licencia'),
                'telephone' => Functions::valueKeyInArray($driver, 'telefono')
            ];
        }
        return null;
    }

    private static function transport($inputs)
    {
        if (key_exists('vehiculo', $inputs)) {
            $transport = $inputs['vehiculo'];
            return [
                'plate_number' => Functions::valueKeyInArray($transport, 'numero_de_placa'),
                'model' => Functions::valueKeyInArray($transport, 'modelo'),
                'brand' => Functions::valueKeyInArray($transport, 'marca'),
                'tuc' => Functions::valueKeyInArray($transport, 'certificado_habilitacion_vehicular'),
            ];
        }
        return null;
    }

    private static function items($inputs)
    {
        if (key_exists('items', $inputs)) {
            $items = [];
            foreach ($inputs['items'] as $row) {
                $items[] = [
                    //'internal_id' => $row['codigo_interno'],
                    //'quantity' => Functions::valueKeyInArray($row, 'cantidad'),

                    'internal_id' => isset($row['codigo_interno']) ? $row['codigo_interno'] : '',
                    'description' => Functions::valueKeyInArray($row, 'descripcion'),
                    'name' => Functions::valueKeyInArray($row, 'nombre'),
                    'second_name' => Functions::valueKeyInArray($row, 'nombre_secundario'),
                    'item_type_id' => Functions::valueKeyInArray($row, 'codigo_tipo_item', '01'),
                    'item_code' => Functions::valueKeyInArray($row, 'codigo_producto_sunat'),
                    'item_code_gs1' => Functions::valueKeyInArray($row, 'codigo_producto_gsl'),
                    'unit_type_id' => Functions::valueKeyInArray($row, 'unidad_de_medida'),
                    'currency_type_id' => 'PEN',

                    'quantity' => Functions::valueKeyInArray($row, 'cantidad'),
                    'unit_value' => Functions::valueKeyInArray($row, 'valor_unitario'),
                    'price_type_id' => Functions::valueKeyInArray($row, 'codigo_tipo_precio'),
                    'unit_price' => Functions::valueKeyInArray($row, 'precio_unitario'),

                    'affectation_igv_type_id' => Functions::valueKeyInArray($row, 'codigo_tipo_afectacion_igv'),
                    'total_base_igv' => Functions::valueKeyInArray($row, 'total_base_igv'),
                    'percentage_igv' => Functions::valueKeyInArray($row, 'porcentaje_igv'),
                    'total_igv' => Functions::valueKeyInArray($row, 'total_igv'),

                    'system_isc_type_id' => Functions::valueKeyInArray($row, 'codigo_tipo_sistema_isc'),
                    'total_base_isc' => Functions::valueKeyInArray($row, 'total_base_isc'),
                    'percentage_isc' => Functions::valueKeyInArray($row, 'porcentaje_isc'),
                    'total_isc' => Functions::valueKeyInArray($row, 'total_isc'),

                    'total_base_other_taxes' => Functions::valueKeyInArray($row, 'total_base_otros_impuestos'),
                    'percentage_other_taxes' => Functions::valueKeyInArray($row, 'porcentaje_otros_impuestos'),
                    'total_other_taxes' => Functions::valueKeyInArray($row, 'total_otros_impuestos'),
                    'total_plastic_bag_taxes' => Functions::valueKeyInArray($row, 'total_impuestos_bolsa_plastica'),

                    'total_taxes' => Functions::valueKeyInArray($row, 'total_impuestos'),
                    'total_value' => Functions::valueKeyInArray($row, 'total_valor_item'),
                    'total_charge' => Functions::valueKeyInArray($row, 'total_cargos'),
                    'total_discount' => Functions::valueKeyInArray($row, 'total_descuentos'),
                    'total' => Functions::valueKeyInArray($row, 'total_item'),

                    'additional_information' => Functions::valueKeyInArray($row, 'informacion_adicional'),
                    'additional_data' => Functions::valueKeyInArray($row, 'dato_adicional'),
                ];
            }

            return $items;
        }
        return null;
    }

    private static function secondary_transports($inputs)
    {
        if (array_key_exists('vehiculo_secundario', $inputs) && isset($inputs['vehiculo_secundario']) && is_array($inputs['vehiculo_secundario'])) {
            $transports = [];
            foreach (array_slice($inputs['vehiculo_secundario'],0,2) as $row) {

                $temp = [
                    'plate_number' => Functions::valueKeyInArray($row, 'numero_de_placa'),
                    'model' => Functions::valueKeyInArray($row, 'modelo'),
                    'brand' => Functions::valueKeyInArray($row, 'marca'),
                    'tuc' => Functions::valueKeyInArray($row, 'certificado_habilitacion_vehicular')
                ];

                $transports[] = $temp;
            }
            return $transports;
        }
        return null;
    }

    private static function secondary_drivers($inputs)
    {
        if (array_key_exists('chofer_secundario', $inputs)&& isset($inputs['chofer_secundario']) && is_array($inputs['chofer_secundario'])) {
            $drivers = [];
            foreach (array_slice($inputs['chofer_secundario'],0,2) as $row) {
                $temp = [
                    'identity_document_type_id' => Functions::valueKeyInArray($row, 'codigo_tipo_documento_identidad'),
                    'number' => Functions::valueKeyInArray($row, 'numero_documento'),
                    'name' => Functions::valueKeyInArray($row, 'nombres'),
                    'license' => Functions::valueKeyInArray($row, 'numero_licencia'),
                    'telephone' => Functions::valueKeyInArray($row, 'telefono'),
                ];

                $drivers[] = $temp;
            }
            return $drivers;
        }
        return null;
    }

    private static function payer($inputs)
    {
        if (key_exists('pagador_flete', $inputs)) {
            $payer = $inputs['pagador_flete'];

            if(!isset($payer['indicador_pagador_flete'])){
                return null;
            }

            return [
                'description' => Functions::valueKeyInArray($payer, 'indicador_pagador_flete'),
                'identity_document_type_id' => Functions::valueKeyInArray($payer, 'codigo_tipo_documento_identidad'),
                'identity_document_type_description' => Functions::valueKeyInArray($payer, 'descripcion_tipo_documento_identidad'),
                'number' => Functions::valueKeyInArray($payer, 'numero'),
                'name' => Functions::valueKeyInArray($payer, 'nombres'),
            ];
        }
        return null;
    }

    private static function documentRelated($inputs)
    {
        if (key_exists('documento_relacionado', $inputs)) {
            $documents = [];

            foreach ($inputs['documento_relacionado'] as $row) {
                $documents[] = [
                    'number' => Functions::valueKeyInArray($row, 'numero'),
                    'name' => Functions::valueKeyInArray($row, 'empresa'),
                    'customer' => Functions::valueKeyInArray($row, 'ruc'),
                    'document_type' => self::documentType($row['documento']),
                ];
            }

            return $documents;
        }
        return null;
    }

    private static function documentType($row)
    {
        $document = [
            'id' => Functions::valueKeyInArray($row,'id'),
            'description' => Functions::valueKeyInArray($row,'descripcion')
        ];

        return $document;
    }

}

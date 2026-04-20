<?php

namespace App\CoreFacturalo\Requests\Inputs;

use App\CoreFacturalo\Requests\Inputs\Common\ActionInput;
use App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput;
use App\CoreFacturalo\Requests\Inputs\Common\LegendInput;
use App\CoreFacturalo\Requests\Inputs\Common\PersonInput;
use App\Models\Tenant\Buyer;
use App\Models\Tenant\Company;
use App\Models\Tenant\Dispatch;
use App\Models\Tenant\Item;
use Illuminate\Support\Str;
use Modules\Dispatch\Models\DispatchAddress;
use Modules\Dispatch\Models\Dispatcher;
use Modules\Dispatch\Models\DispatchPerson;
use Modules\Dispatch\Models\Driver;
use Modules\Dispatch\Models\Receiver;
use Modules\Dispatch\Models\ReceiverAddress;
use Modules\Dispatch\Models\Sender;
use Modules\Dispatch\Models\SenderAddress;
use Modules\Dispatch\Models\Transport;
use App\Models\Tenant\Catalogs\District;

class DispatchInput
{
    public static function set($inputs)
    {
        $document_type_id = $inputs['document_type_id'];
        $series = $inputs['series'];
        $number = $inputs['number'];

        $company = Company::active();
        $soap_type_id = $company->soap_type_id;
        $number = Functions::newNumber($soap_type_id, $document_type_id, $series, $number, Dispatch::class);

        if (is_null($inputs['id'])) {
            Functions::validateUniqueDocument($soap_type_id, $document_type_id, $series, $number, Dispatch::class);
        }

        $filename = Functions::filename($company, $document_type_id, $series, $number);
        $establishment = EstablishmentInput::set($inputs['establishment_id']);
        $customer = self::customer($inputs);
        $inputs['type'] = 'dispatch';
        $data = [
            'id' => Functions::valueKeyInArray($inputs, 'id'),
            'type' => $inputs['type'],
            'user_id' => auth()->id(),
            'external_id' => Str::uuid()->toString(),
            'establishment_id' => $inputs['establishment_id'],
            'establishment' => $establishment,
            'soap_type_id' => $soap_type_id,
            'state_type_id' => '01',
            'ubl_version' => '2.0',
            'filename' => $filename,
            'document_type_id' => $document_type_id,
            'series' => $series,
            'number' => $number,
            'date_of_issue' => $inputs['date_of_issue'],
            'time_of_issue' => $inputs['time_of_issue'],
            'customer_id' => self::customer_id($inputs),
            'customer' => $customer,
            'observations' => $inputs['observations'],
            'transport_mode_type_id' => Functions::valueKeyInArray($inputs, 'transport_mode_type_id'),
            'transfer_reason_type_id' => Functions::valueKeyInArray($inputs, 'transfer_reason_type_id'),
            'transfer_reason_description' => Functions::valueKeyInArray($inputs, 'transfer_reason_description'),
            'date_of_shipping' => $inputs['date_of_shipping'],
            'transshipment_indicator' => $inputs['transshipment_indicator'],
            'port_code' => $inputs['port_code'],
            'unit_type_id' => $inputs['unit_type_id'],
            'total_weight' => $inputs['total_weight'],
            'packages_number' => $inputs['packages_number'],
            'container_number' => $inputs['container_number'],
//            'license_plate' => (isset($inputs['license_plate'])) ? func_str_to_upper_utf8($inputs['license_plate']) : null,
            'origin' => self::origin($inputs),
            'delivery' => self::delivery($inputs),
            'dispatcher' => self::dispatcher($inputs),
            'driver' => self::driver($inputs),
            'transport_data' => self::transport($inputs),
            'items' => self::items($inputs),
            'legends' => LegendInput::set($inputs),
            'optional' => Functions::valueKeyInArray($inputs, 'optional'),
            'actions' => ActionInput::set($inputs),
            'reference_document_id' => Functions::valueKeyInArray($inputs, 'reference_document_id'),
            'reference_quotation_id' => Functions::valueKeyInArray($inputs, 'reference_quotation_id'),
            'reference_order_note_id' => Functions::valueKeyInArray($inputs, 'reference_order_note_id'),
            'reference_order_form_id' => Functions::valueKeyInArray($inputs, 'reference_order_form_id'),
            'reference_sale_note_id' => Functions::valueKeyInArray($inputs, 'reference_sale_note_id'),
            'secondary_license_plates' => self::secondary_license_plates($inputs),
            'related' => self::related($inputs),
            'order_form_external' => Functions::valueKeyInArray($inputs, 'order_form_external'),
            'additional_data' => Functions::valueKeyInArray($inputs, 'additional_data'),
            'origin_address_id' => Functions::valueKeyInArray($inputs, 'origin_address_id', 0),
            'delivery_address_id' => self::getDeliveryId($inputs),
            // 'delivery_address_id' => Functions::valueKeyInArray($inputs, 'delivery_address_id', null),
            'driver_id' => self::getDriverId($inputs),
            'dispatcher_id' => self::getDispatcherId($inputs),
            'sender_id' => self::getSenderId($inputs),
            'receiver_id' => self::getReceiverId($inputs),
            'sender_address_id' => self::getSenderAddressId($inputs),
            'receiver_address_id' => self::getReceiverAddressId($inputs),
            'sender_data' => self::senderData($inputs),
            'receiver_data' => self::receiverData($inputs),
            'sender_address_data' => self::senderAddressData($inputs),
            'receiver_address_data' => self::receiverAddressData($inputs),
            'date_delivery_to_transport' => Functions::valueKeyInArray($inputs, 'date_delivery_to_transport'),
            'secondary_transports' => self::secondary_transports($inputs),
            'secondary_drivers' => self::secondary_drivers($inputs),
            'payer' => self::payer($inputs),
            'buyer_id' => self::getBuyerId($inputs),
            'buyer' => self::buyer($inputs),
            'has_transport_driver_01' => Functions::valueKeyInArray($inputs, 'has_transport_driver_01'),
            'is_transport_m1l' => Functions::valueKeyInArray($inputs, 'is_transport_m1l'),
            'license_plate_m1l' => Functions::valueKeyInArray($inputs, 'license_plate_m1l'),
            'reference_documents' => $inputs['reference_documents'],
            'custom_fields_data' => Functions::valueKeyInArray($inputs, 'custom_fields_data'),
        ];

        if (isset($inputs['data_affected_document'])) {
            $data['data_affected_document'] = $inputs['data_affected_document'];
        }
        // dd($data);
        return $data;
    }

    private static function customer($inputs)
    {
        if(array_key_exists('customer_id', $inputs)) {
            return PersonInput::set($inputs['customer_id']);
        }
        return null;
    }

    private static function customer_id($inputs)
    {
        if(array_key_exists('customer_id', $inputs)) {
            return $inputs['customer_id'];
        }

        return null;
    }

    /**
     *
     * Documento relacionado (DAM), usado para exportación
     *
     * @param  $inputs
     * @return array|null
     */
    private static function related($inputs)
    {
        if (array_key_exists('related', $inputs)) {
            $related = $inputs['related'];

            if (!empty($related)) return $related;
        }

        return null;
    }


    private static function origin($inputs)
    {
        if($inputs['document_type_id'] == '09') {
            if (array_key_exists('origin', $inputs)) {
                $origin = $inputs['origin'];
                $country_id = key_exists('country_id', $origin) ? $origin['country_id'] : 'PE';
                $address = $origin['address'];
                $location_id = $origin['location_id'][2] == '0' ? $origin['location_id'] : $origin['location_id'][2];
                $code = key_exists('code', $origin) ? $origin['code'] : '0000';

                return [
                    'country_id' => $country_id,
                    'location_id' => $location_id,
                    'address' => $address,
                    'code' => $code,
                ];
            }
        }
        return null;
    }

    private static function delivery($inputs)
    {
        if($inputs['document_type_id'] == '09') {
            if (array_key_exists('delivery', $inputs)) {
                $delivery = $inputs['delivery'];
                $country_id = key_exists('country_id', $delivery) ? $delivery['country_id'] : 'PE';
                $address = $delivery['address'];
                $location_id = is_array($delivery['location_id']) ? $delivery['location_id'][2] : $delivery['location_id'];
                $code = key_exists('code', $delivery) ? $delivery['code'] : '0000';

                return [
                    'country_id' => $country_id,
                    'location_id' => $location_id,
                    'address' => $address,
                    'code' => $code,
                ];
            }
        }
        return null;
    }

    private static function dispatcher($inputs)
    {
        if ($inputs['document_type_id'] === '09' && $inputs['transport_mode_type_id'] === '01') {
            if (array_key_exists('dispatcher', $inputs)) {
                $dispatcher = $inputs['dispatcher'];
                $identity_document_type_id = $dispatcher['identity_document_type_id'];
                $number = $dispatcher['number'];
                $name = $dispatcher['name'];
                $number_mtc = (isset($dispatcher['number_mtc'])) ? $dispatcher['number_mtc'] : null;

                return [
                    'identity_document_type_id' => $identity_document_type_id,
                    'number' => $number,
                    'name' => $name,
                    'number_mtc' => $number_mtc,
                ];
            }
        }
        return null;
    }

    private static function driver($inputs)
    {
        $has_transport_driver = isset($inputs['has_transport_driver_01']) ? $inputs['has_transport_driver_01'] : false;

        if ((($inputs['document_type_id'] === '09') && ($inputs['transport_mode_type_id'] === '02'||$has_transport_driver===true)) || $inputs['document_type_id'] === '31') {
            if (array_key_exists('driver', $inputs) && isset($inputs['driver'])) {
                $driver = $inputs['driver'];
                $identity_document_type_id = $driver['identity_document_type_id'];
                $number = $driver['number'];
                $name = $driver['name'];
                $license = $driver['license'];
                $telephone = $driver['telephone'];

                return [
                    'identity_document_type_id' => $identity_document_type_id,
                    'number' => $number,
                    'name' => $name,
                    'license' => $license,
                    'telephone' => $telephone,
                ];
            }
        }

        return null;
    }

    private static function transport($inputs)
    {
        $has_transport_driver = isset($inputs['has_transport_driver_01']) ? $inputs['has_transport_driver_01'] : false;

        if ((($inputs['document_type_id'] === '09') && ($inputs['transport_mode_type_id'] === '02'||$has_transport_driver===true)) || $inputs['document_type_id'] === '31') {
            if (array_key_exists('transport', $inputs) && isset($inputs['transport'])) {
                $transport = $inputs['transport'];
                $plate_number = $transport['plate_number'];
                $model = $transport['model'];
                $brand = $transport['brand'];
                $tuc = $transport['tuc'];

                return [
                    'plate_number' => $plate_number,
                    'model' => $model,
                    'brand' => $brand,
                    'tuc' => $tuc
                ];
            }
        }

        return null;
    }

    private static function senderData($inputs)
    {
        if ($inputs['document_type_id'] === '31') {
            if (array_key_exists('sender_data', $inputs)) {
                $sender = $inputs['sender_data'];
                $identity_document_type_id = $sender['identity_document_type_id'];
                $identity_document_type_description = $sender['identity_document_type_description'];
                $number = $sender['number'];
                $name = $sender['name'];

                return [
                    'identity_document_type_id' => $identity_document_type_id,
                    'identity_document_type_description' => $identity_document_type_description,
                    'number' => $number,
                    'name' => $name,
                ];
            }
        }

        return null;
    }

    private static function receiverData($inputs)
    {
        if ($inputs['document_type_id'] === '31') {
            if (array_key_exists('receiver_data', $inputs)) {
                $receiver = $inputs['receiver_data'];
                $identity_document_type_id = $receiver['identity_document_type_id'];
                $identity_document_type_description = $receiver['identity_document_type_description'];
                $number = $receiver['number'];
                $name = $receiver['name'];

                return [
                    'identity_document_type_id' => $identity_document_type_id,
                    'identity_document_type_description' => $identity_document_type_description,
                    'number' => $number,
                    'name' => $name,
                ];
            }
        }

        return null;
    }

    private static function receiverAddressData($inputs)
    {
        if ($inputs['document_type_id'] === '31') {
            if (array_key_exists('receiver_address_data', $inputs)) {
                $address = $inputs['receiver_address_data'];
                $location_id = $address['location_id'][2];
                $address = $address['address'];

                return [
                    'location_id' => $location_id,
                    'address' => $address
                ];
            }
        }

        return null;
    }

    private static function senderAddressData($inputs)
    {
        if ($inputs['document_type_id'] === '31') {
            if (array_key_exists('sender_address_data', $inputs)) {
                $address = $inputs['sender_address_data'];
                $location_id = $address['location_id'][2];
                $address = $address['address'];

                return [
                    'location_id' => $location_id,
                    'address' => $address
                ];
            }
        }

        return null;
    }

    private static function items($inputs)
    {
        if (array_key_exists('items', $inputs)) {
            $items = [];
            foreach ($inputs['items'] as $row) {
                $item = Item::find($row['item_id']);
                $itemDispatch = $row['item'] ?? [];
                $row['IdLoteSelected'] = $row['IdLoteSelected'] ?? $itemDispatch['IdLoteSelected'] ?? null;

                if(!$row['IdLoteSelected']){
                    $row['IdLoteSelected'] = isset($itemDispatch['item'])?isset($itemDispatch['item']['IdLoteSelected'])?$itemDispatch['item']['IdLoteSelected']:null:null;
                }

                $item_inner =  isset($row['item']) ? $row['item']['item'] : null;

                $temp = [
                    'item_id' => $item->id,
                    'item' => [
                        'description' => ($row['description'])??$item->description,
                        'model' => $item->model,
                        'item_type_id' => $item->item_type_id,
                        'internal_id' => $item->internal_id,
                        'item_code' => $item->item_code,
                        'item_code_gs1' => $item->item_code_gs1,
                        'unit_type_id' => ($row['unit_type_id'])??$item->unit_type_id,
                        'IdLoteSelected' => is_null($item_inner) ? null : Functions::valueKeyInArray($item_inner, 'IdLoteSelected', null),
                        'lot_group' => $row['lot_group'] ?? null,
                        'lots' =>  is_null($item_inner) ? null : Functions::valueKeyInArray($item_inner, 'lots', []),
                        'unit_price' =>isset($row['unit_price'])?$row['unit_price']:null,
                        'total' =>isset($row['total'])?$row['total']:null,
                        'weight' => isset($row['weight'])?$row['weight']:null,
                    ],
                    'quantity' => $row['quantity'],
                    'name_product_pdf' => Functions::valueKeyInArray($row, 'name_product_pdf'),
                    'additional_data' => Functions::valueKeyInArray($row, 'additional_data'),
                ];

                if (isset($temp['item']['lot_group']['date_of_due'])) {
                    $temp['item']['date_of_due'] = $temp['item']['lot_group']['date_of_due'];
                } else {
                    $temp['item']['date_of_due'] = $itemDispatch['date_of_due'] ?? null;
                }
                $items[] = $temp;
            }
            return $items;
        }
        return null;
    }

    private static function secondary_license_plates($inputs)
    {
        if (array_key_exists('secondary_license_plates', $inputs)) {
            $secondary_license_plates = $inputs['secondary_license_plates'];
            $semitrailer = $secondary_license_plates['semitrailer'];
            return [
                'semitrailer' => $semitrailer,
            ];

        }
        return null;
    }

    private static function getDispatcherId($inputs)
    {
        $is_transport_m1l = isset($inputs['is_transport_m1l']) ? $inputs['is_transport_m1l'] : false;
        if (!$is_transport_m1l) {
            if ($inputs['document_type_id'] === '09' && $inputs['transport_mode_type_id'] === '01') {
            $dispatcher = $inputs['dispatcher'];
            $record = Dispatcher::query()
                ->firstOrCreate([
                    'identity_document_type_id' => $dispatcher['identity_document_type_id'],
                    'number' => $dispatcher['number']
                ], [
                    'name' => $dispatcher['name'],
                    'number_mtc' => $dispatcher['number_mtc'],
                    'address' => '-'
                ]);

            return $record->id;
            }
        }
        return null;
    }


    private static function getDeliveryId($inputs)
    {
        $delivery_address_id = Functions::valueKeyInArray($inputs, 'delivery_address_id', null);
        if($delivery_address_id != null) {
            return $delivery_address_id;
        }
        if ($inputs['document_type_id'] === '09' && $inputs['transport_mode_type_id'] === '01') {
            $delivery = $inputs['delivery'];

            $location = $delivery['location_id'];
            if(is_string($location)){
                $district = District::find($delivery['location_id']);
                $location = [$district->province->department->id, $district->province->id, $district->id];
            }
            $record = DispatchAddress::query()
                ->where([
                'person_id' => self::getDispatcherId($inputs),
                'address' => $delivery['address']])
                ->first();
            if(!$record){
                $record = DispatchAddress::query()
                    ->create([
                        'person_id' => self::getDispatcherId($inputs),
                        'location_id' => $location,
                        'address' => $delivery['address']
                    ]);
            }
           return $record->id;
        }
        return null;
    }

    private static function getDriverId($inputs)
    {
        // dd($inputs);
        $is_transport_m1l = isset($inputs['is_transport_m1l']) ? $inputs['is_transport_m1l'] : false;
        if (!$is_transport_m1l) {
            if (($inputs['document_type_id'] === '09' && $inputs['transport_mode_type_id'] === '02') || $inputs['document_type_id'] === '31') {
    //            if (key_exists('driver_id', $inputs)) {
                    // return $inputs['driver_id'];
    //            }
                $driver = $inputs['driver'];
                $record = Driver::query()
                    ->firstOrCreate([
                        'identity_document_type_id' => $driver['identity_document_type_id'],
                        'number' => $driver['number']
                    ], [
                        'name' => $driver['name'],
                        'license' => $driver['license'],
                        'telephone' => $driver['telephone']
                    ]);

                return $record->id;
            }
            return null;
        }
    }

    private static function getTransportId($inputs)
    {
        if (($inputs['document_type_id'] === '09' && $inputs['transport_mode_type_id'] === '02')  || $inputs['document_type_id'] === '31') {
//            if (key_exists('transport_id', $inputs)) {
                return $inputs['transport_id'];
//            }
//            $transport = $inputs['transport'];
//            $record = Transport::query()
//                ->firstOrCreate([
//                    'plate_number' => $transport['plate_number']
//                ], [
//                    'model' => $transport['model'],
//                    'brand' => $transport['brand']
//                ]);
//
//            return $record->id;
        }
        return null;
    }

    private static function getSenderId($inputs)
    {
        if ( $inputs['document_type_id'] === '31') {
            if (key_exists('sender_id', $inputs)) {
                return $inputs['sender_id'];
            }
//            $sender = $inputs['sender'];
//            $record = DispatchPerson::query()
//                ->firstOrCreate([
//                    'identity_document_type_id' => $sender['identity_document_type_id'],
//                    'number' => $sender['number'],
//                ], [
//                    'name' => $sender['name']
//                ]);
//
//            return $record->id;
        }
        return null;
    }

    private static function getReceiverId($inputs)
    {
        if ( $inputs['document_type_id'] === '31') {
            if (key_exists('receiver_id', $inputs)) {
                return $inputs['receiver_id'];
            }
//            $receiver = $inputs['receiver'];
//            $record = DispatchPerson::query()
//                ->firstOrCreate([
//                    'identity_document_type_id' => $receiver['identity_document_type_id'],
//                    'number' => $receiver['number'],
//                ], [
//                    'name' => $receiver['name']
//                ]);
//
//            return $record->id;
        }
        return null;
    }

    private static function getReceiverAddressId($inputs)
    {
        return null;
        if ( $inputs['document_type_id'] === '31') {
            return $inputs['receiver_address_id'];
//            if (key_exists('receiver_address_id', $inputs)) {
//            }
//            $address = $inputs['receiver_address'];
//            $record = DispatchAddress::query()
//                ->firstOrCreate([
//                    'person_id' => $inputs['receiver_id'],
//                    'location_id' => $address['location_id'],
//                    'address' => $address['address']
//                ]);
//
//            return $record->id;
        }
        return null;
    }

    private static function getSenderAddressId($inputs)
    {
        return null;
        if ( $inputs['document_type_id'] === '31') {
            return $inputs['sender_address_id'];
//            if (key_exists('sender_address_id', $inputs)) {
//
//            }
//            $address = $inputs['sender_address'];
//            $record = DispatchAddress::query()
//                ->firstOrCreate([
//                    'person_id' => $inputs['sender_id'],
//                    'location_id' => $address['location_id'],
//                    'address' => $address['address']
//                ]);
//
//            return $record->id;
        }
        return null;
    }
    private static function secondary_transports($inputs)
    {
        if (array_key_exists('secondary_transports', $inputs) && isset($inputs['secondary_transports']) && is_array($inputs['secondary_transports'])) {
            $transports = [];
            foreach (array_slice($inputs['secondary_transports'],0,2) as $row) {

                $temp = [
                    'plate_number' => $row['plate_number'],
                    'model' => $row['model'],
                    'brand' => $row['brand'],
                    'tuc' => $row['tuc']
                ];

                $transports[] = $temp;
            }
            return $transports;
        }
        return null;
    }

    private static function secondary_drivers($inputs)
    {
        if (array_key_exists('secondary_drivers', $inputs)&& isset($inputs['secondary_drivers']) && is_array($inputs['secondary_drivers'])) {
            $drivers = [];
            foreach (array_slice($inputs['secondary_drivers'],0,2) as $row) {
                $temp = [
                    'identity_document_type_id' => $row['identity_document_type_id'],
                    'number' => $row['number'],
                    'name' => $row['name'],
                    'license' => $row['license'],
                    'telephone' => $row['telephone'],
                ];

                $drivers[] = $temp;
            }
            return $drivers;
        }
        return null;
    }

    private static function payer($inputs)
    {
        if (key_exists('payer', $inputs)) {
            $payer = $inputs['payer'];

            if(!isset($payer['description'])){
                return null;
            }
            return $payer;
        }
        return null;
    }

    private static function getBuyerId($inputs)
    {
        if ( isset($inputs['transfer_reason_type_id']) && ($inputs['transfer_reason_type_id'] === '03')) {
            $record = Buyer::firstOrCreate([
                'identity_document_type_id' => $inputs['buyer']['identity_document_type_id'],
                'number' => $inputs['buyer']['number']
            ], [
                'name' => $inputs['buyer']['name'],
                'address' => $inputs['buyer']['address'],
                'location_id' => $inputs['buyer']['location_id']
            ]);
            return $record->id;
        }
        return null;
    }

    private static function buyer($inputs)
    {
        if (key_exists('buyer', $inputs)) {
            $buyer = $inputs['buyer'];
            return [
                'identity_document_type_id' => Functions::valueKeyInArray($buyer, 'identity_document_type_id'),
                'number' => Functions::valueKeyInArray($buyer, 'number'),
                'name' => Functions::valueKeyInArray($buyer, 'name'),
                'address' => Functions::valueKeyInArray($buyer, 'address'),
                'location_id' => Functions::valueKeyInArray($buyer, 'location_id'),
            ];
        }
        return null;
    }

}

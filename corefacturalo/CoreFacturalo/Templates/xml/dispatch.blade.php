@php
    
    if($document['transfer_reason_type_id']==='04'){
        $document['customer_identity_document_type_id'] = '6';
        $document['customer_number'] = $document['company_number'];
        $document['customer_name'] = $document['company_name'];
    }

    $sellerPresence = \App\CoreFacturalo\Helpers\Template\TemplateHelper::sellerPresence($document);
    $sameAsSender = \App\CoreFacturalo\Helpers\Template\TemplateHelper::sameAsSender($document);
    $sellerSupplierPresence = \App\CoreFacturalo\Helpers\Template\TemplateHelper::sellerSupplierPresence($document);
@endphp

{!!  '<'.'?xml version="1.0" encoding="utf-8" standalone="no"?'.'>'  !!}
<DespatchAdvice xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
                xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
                xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
                xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"
                xmlns="urn:oasis:names:specification:ubl:schema:xsd:DespatchAdvice-2">
    <ext:UBLExtensions>
        <ext:UBLExtension>
            <ext:ExtensionContent/>
        </ext:UBLExtension>
    </ext:UBLExtensions>
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>2.0</cbc:CustomizationID>
    <cbc:ID>{{ $document['series'] }}-{{ $document['number'] }}</cbc:ID>
    <cbc:IssueDate>{{ $document['date_of_issue'] }}</cbc:IssueDate>
    <cbc:IssueTime>{{ $document['time_of_issue'] }}</cbc:IssueTime>
    <cbc:DespatchAdviceTypeCode listURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01"
                                listName="Tipo de Documento"
                                listAgencyName="PE:SUNAT">{{ $document['document_type_id'] }}</cbc:DespatchAdviceTypeCode>
    @if($document['observations'])
        <cbc:Note><![CDATA[{{ $document['observations'] }}]]></cbc:Note>
    @endif

    @if($document['reference_documents'])
        @foreach($document['reference_documents'] as $row)
        <!--  DOCUMENTOS ADICIONALES (Catalogo D41) -->
        <cac:AdditionalDocumentReference>
            <cbc:ID>{{ $row['number'] }}</cbc:ID>
            <cbc:DocumentTypeCode listAgencyName="PE:SUNAT" listName="Documento relacionado al transporte" listURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo61">{{ $row['document_type']['id'] }}</cbc:DocumentTypeCode>
            <cbc:DocumentType>{{ $row['document_type']['description'] }}</cbc:DocumentType>
            <cac:IssuerParty>
                <cac:PartyIdentification>
                    <cbc:ID schemeID="6" schemeAgencyName="PE:SUNAT" schemeURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06">{{ $document['company_number'] }}</cbc:ID>
                </cac:PartyIdentification>
            </cac:IssuerParty>
        </cac:AdditionalDocumentReference>
        @endforeach
    @endif
    <!-- Signature -->

    @if($document['transfer_reason_type_id']=='09' && $document['related_number'])
        <cac:AdditionalDocumentReference>
            <cbc:ID>{{ $document['related_number'] }}</cbc:ID>
            <cbc:DocumentTypeCode listAgencyName="PE:SUNAT" listName="Documento relacionado al transporte" listURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo61">{{ $document['related_document_type_id'] }}</cbc:DocumentTypeCode>
        </cac:AdditionalDocumentReference>
    @endif
    <cac:Signature>
        <cbc:ID>{{ config('configuration.signature_uri') }}</cbc:ID>
        <cbc:Note>{{ config('configuration.signature_note') }}</cbc:Note>
        <cac:SignatoryParty>
            <cac:PartyIdentification>
                <cbc:ID>{{ $document['company_number'] }}</cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyName>
                <cbc:Name><![CDATA[{{ $document['company_name'] }}]]></cbc:Name>
            </cac:PartyName>
        </cac:SignatoryParty>
        <cac:DigitalSignatureAttachment>
            <cac:ExternalReference>
                <cbc:URI>#{{ config('configuration.signature_uri') }}</cbc:URI>
            </cac:ExternalReference>
        </cac:DigitalSignatureAttachment>
    </cac:Signature>
    <cac:DespatchSupplierParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06"
                        schemeAgencyName="PE:SUNAT"
                        schemeName="Documento de Identidad"
                        schemeID="6">{{ $document['company_number'] }}</cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName><![CDATA[{{ $document['company_name'] }}]]></cbc:RegistrationName>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:DespatchSupplierParty>
    <cac:DeliveryCustomerParty>
        <cac:Party>
            <cac:PartyIdentification>
                @php
                    $schemeIdDeliveryCustomer = $sameAsSender ? '6' : $document['customer_identity_document_type_id'];
                    $valueDeliveryCustomer = $sameAsSender ? $document['company_number'] : $document['customer_number'];
                @endphp
                <cbc:ID schemeURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06"
                        schemeAgencyName="PE:SUNAT"
                        schemeName="Documento de Identidad"
                        schemeID="{{ $schemeIdDeliveryCustomer }}">{{ $valueDeliveryCustomer }}</cbc:ID> 
            </cac:PartyIdentification>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName><![CDATA[{{ $sameAsSender ? $document['company_name'] : $document['customer_name'] }}]]></cbc:RegistrationName>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:DeliveryCustomerParty>
    @if ($sellerSupplierPresence)
    <!--  DATOS DEL PROVEEDOR  -->
        <cac:SellerSupplierParty>
            <cac:Party>
                <cac:PartyIdentification>
                    <cbc:ID schemeID="{{ $document['customer_identity_document_type_id'] }}" schemeName="Documento de Identidad" schemeAgencyName="PE:SUNAT" schemeURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06">{{ $document['customer_number'] }}</cbc:ID>
                </cac:PartyIdentification>
                <cac:PartyLegalEntity>
                    <cbc:RegistrationName>![CDATA[{{ $document['customer_name'] }}]]</cbc:RegistrationName>
                </cac:PartyLegalEntity>
            </cac:Party>
        </cac:SellerSupplierParty>
    @endif
    @if (isset($document['buyer']) && ($document['transfer_reason_type_id'] === '03'))
        <cac:BuyerCustomerParty>
            <cac:Party>
                <cac:PartyIdentification>
                    <cbc:ID schemeURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06"
                        schemeAgencyName="PE:SUNAT"
                        schemeName="Documento de Identidad"
                        schemeID="{{ $document['buyer']['identity_document_type_id'] }}"
                    >{{ $document['buyer']['number'] }}</cbc:ID>
                </cac:PartyIdentification>
                <cac:PartyLegalEntity>
                    <cbc:RegistrationName><![CDATA[{{ $document['buyer']['name'] }}]]></cbc:RegistrationName>
                </cac:PartyLegalEntity>
            </cac:Party>
        </cac:BuyerCustomerParty>
        
    @endif
    <cac:Shipment>
        <!-- ID OBLIGATORIO POR UBL -->
        <cbc:ID>1</cbc:ID>
        <!-- MOTIVO DEL TRASLADO -->
        <cbc:HandlingCode listURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo20"
                          listName="Motivo de traslado"
                          listAgencyName="PE:SUNAT">{{ $document['transfer_reason_type_id'] }}</cbc:HandlingCode>
        <cbc:HandlingInstructions>{{ $document['transfer_reason_type_name'] }}</cbc:HandlingInstructions>
        <!-- PESO BRUTO TOTAL DE LA CARGA-->
        <cbc:GrossWeightMeasure
            unitCode="{{ $document['unit_type_id'] }}">{{ $document['total_weight'] }}</cbc:GrossWeightMeasure>
        
        @if($document['transport_tuc'] && $document['transport_mode_type_id'] != '02' && $document['has_transport_driver_01'] ==true)
            <cbc:SpecialInstructions>SUNAT_Envio_IndicadorVehiculoConductoresTransp</cbc:SpecialInstructions>
        @endif

        @if($document['is_transport_m1l']==true)
            <cbc:SpecialInstructions>SUNAT_Envio_IndicadorTrasladoVehiculoM1L</cbc:SpecialInstructions>
        @endif

        @if(!$document['is_transport_m1l'])
            @if($document['packages_number'] && $document['has_transport_driver_01'] ==false)
                <cbc:TotalTransportHandlingUnitQuantity>{{ $document['packages_number'] }}</cbc:TotalTransportHandlingUnitQuantity>
            @endif
        @endif
        @if($document['transfer_reason_type_id']=='09')
            <cbc:SpecialInstructions>SUNAT_Envio_IndicadorTrasladoTotalDAMoDS</cbc:SpecialInstructions>
        @endif
        <cac:ShipmentStage>
            <!-- MODALIDAD DE TRASLADO -->
            <cbc:TransportModeCode listURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo18"
                                   listName="Modalidad de traslado"
                                   listAgencyName="PE:SUNAT">{{ $document['transport_mode_type_id'] }}</cbc:TransportModeCode>
            <!-- FECHA DE INICIO DEL TRASLADO o FECHA DE ENTREGA DE BIENES AL TRANSPORTISTA -->
            <cac:TransitPeriod>
                <cbc:StartDate>{{ $document['date_of_shipping'] }}</cbc:StartDate>
            </cac:TransitPeriod>
            @if($document['transport_mode_type_id'] === '01' && !$document['is_transport_m1l'])
                <cac:CarrierParty>
                    <cac:PartyIdentification>
                        <cbc:ID schemeURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06"
                                schemeAgencyName="PE:SUNAT"
                                schemeName="Documento de Identidad"
                                schemeID="{{ $document['dispatcher_identity_document_type_id'] }}">{{ $document['dispatcher_number'] }}</cbc:ID>
                    </cac:PartyIdentification>
                    <cac:PartyLegalEntity>
                        <!-- NOMBRE/RAZON SOCIAL DEL TRANSPORTISTA-->
                        <cbc:RegistrationName><![CDATA[{{ $document['dispatcher_name'] }}]]></cbc:RegistrationName>
                        <!-- NUMERO DE REGISTRO DEL MTC -->
                        <cbc:CompanyID>{{ $document['dispatcher_number_mtc'] }}</cbc:CompanyID>
                    </cac:PartyLegalEntity>
                </cac:CarrierParty>
            @endif
            @if($document['transport_mode_type_id'] === '02' && !$document['is_transport_m1l'])
            <!-- CONDUCTOR PRINCIPAL -->
                <cac:DriverPerson>
                    <!-- TIPO Y NUMERO DE DOCUMENTO DE IDENTIDAD -->
                    <cbc:ID schemeURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06"
                            schemeAgencyName="PE:SUNAT"
                            schemeName="Documento de Identidad"
                            schemeID="{{ $document['driver_identity_document_type_id'] }}">{{ $document['driver_number'] }}</cbc:ID>
                    <!-- NOMBRES -->
                    <cbc:FirstName>{{ $document['driver_names'] }}</cbc:FirstName>
                    <!-- APELLIDOS -->
                    <cbc:FamilyName>{{ $document['driver_lastnames'] }}</cbc:FamilyName>
                    <!-- TIPO DE CONDUCTOR: PRINCIPAL -->
                    <cbc:JobTitle>Principal</cbc:JobTitle>
                    <cac:IdentityDocumentReference>
                        <!-- LICENCIA DE CONDUCIR -->
                        <cbc:ID>{{ $document['driver_license'] }}</cbc:ID>
                    </cac:IdentityDocumentReference>
                </cac:DriverPerson>
                <!-- CONDUCTOR SECUNDARIO -->
                @if($document['secondary_drivers'])
                    @foreach($document['secondary_drivers'] as $row)
                    <cac:DriverPerson>
                        <cbc:ID schemeURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06"
                                schemeAgencyName="PE:SUNAT"
                                schemeName="Documento de Identidad"
                                schemeID="{{ $row['identity_document_type_id'] }}">{{ $row['number'] }}</cbc:ID>
                        <cbc:FirstName>{{ $row['name'] }}</cbc:FirstName>
                        <cbc:FamilyName>{{ $row['name'] }}</cbc:FamilyName>
                        <cbc:JobTitle>Secundario</cbc:JobTitle>
                        <cac:IdentityDocumentReference>
                            <cbc:ID>{{ $row['license'] }}</cbc:ID>
                        </cac:IdentityDocumentReference>
                    </cac:DriverPerson>
                    @endforeach
                @endif
            @endif
        </cac:ShipmentStage>
        <cac:Delivery>
            @if($document['transfer_reason_type_id'] != '02')
                <!-- DIRECCION DEL PUNTO DE LLEGADA -->
                <cac:DeliveryAddress>
                    <!-- UBIGEO DE LLEGADA -->
                    <cbc:ID schemeAgencyName="PE:INEI"
                            schemeName="Ubigeos">{{ $document['delivery_location_id'] }}</cbc:ID>
                    <!-- CODIGO DE ESTABLECIMIENTO ANEXO DE LLEGADA -->
                    @if($document['customer_identity_document_type_id'] === '6' && $document['transfer_reason_type_id']!='18')
                    <cbc:AddressTypeCode listAgencyName="PE:SUNAT"
                                        listName="Establecimientos anexos"
                                        listID="{{ $document['customer_number'] }}">{{ $document['delivery_code'] }}</cbc:AddressTypeCode>
                    @endif
                    <cac:AddressLine>
                        <cbc:Line><![CDATA[{{ $document['delivery_address'] }}]]></cbc:Line>
                    </cac:AddressLine>
                </cac:DeliveryAddress>

                <cac:Despatch>
                    <!-- DIRECCION DEL PUNTO DE PARTIDA -->
                    <cac:DespatchAddress>
                        <!-- UBIGEO DE PARTIDA -->
                        <cbc:ID schemeAgencyName="PE:INEI"
                                schemeName="Ubigeos">{{ $document['origin_location_id'] }}</cbc:ID>
                        <!-- CODIGO DE ESTABLECIMIENTO ANEXO DE PARTIDA -->
                        <cbc:AddressTypeCode listName="Establecimientos anexos"
                                            listAgencyName="PE:SUNAT"
                                            listID="{{ $document['company_number'] }}">0000</cbc:AddressTypeCode>
                        <!-- DIRECCION COMPLETA Y DETALLADA DE PARTIDA -->
                        <cac:AddressLine>
                            <cbc:Line><![CDATA[{{ $document['origin_address'] }}]]></cbc:Line>
                        </cac:AddressLine>
                    </cac:DespatchAddress>
                </cac:Despatch>
            @else
                <!-- DIRECCION DEL PUNTO DE LLEGADA -->
                <cac:DeliveryAddress>
                    <!-- UBIGEO DE LLEGADA -->
                    <cbc:ID schemeAgencyName="PE:INEI"
                            schemeName="Ubigeos">{{ $document['origin_location_id'] }}</cbc:ID>
                    @if($document['transfer_reason_type_id']!='18')
                        <!-- CODIGO DE ESTABLECIMIENTO ANEXO DE LLEGADA -->
                        <cbc:AddressTypeCode listAgencyName="PE:SUNAT"
                                            listName="Establecimientos anexos"
                                            listID="{{ $document['company_number'] }}">0000</cbc:AddressTypeCode>
                    @endif
                    <cac:AddressLine>
                        <cbc:Line><![CDATA[{{ $document['origin_address'] }}]]></cbc:Line>
                    </cac:AddressLine>
                </cac:DeliveryAddress>
                <cac:Despatch>
                    <!-- DIRECCION DEL PUNTO DE PARTIDA -->
                    <cac:DespatchAddress>
                        <!-- UBIGEO DE PARTIDA -->
                        <cbc:ID schemeAgencyName="PE:INEI"
                                schemeName="Ubigeos">{{ $document['delivery_location_id'] }}</cbc:ID>
                        <!-- CODIGO DE ESTABLECIMIENTO ANEXO DE PARTIDA -->
                        <cbc:AddressTypeCode listName="Establecimientos anexos"
                                            listAgencyName="PE:SUNAT"
                                            listID="{{ $document['customer_number'] }}">{{ $document['delivery_code'] }}</cbc:AddressTypeCode>
                        <!-- DIRECCION COMPLETA Y DETALLADA DE PARTIDA -->
                        <cac:AddressLine>
                            <cbc:Line><![CDATA[{{ $document['delivery_address'] }}]]></cbc:Line>
                        </cac:AddressLine>
                    </cac:DespatchAddress>
                </cac:Despatch>
            @endif
        </cac:Delivery>
        @if(($document['transport_mode_type_id'] === '02' && !$document['is_transport_m1l']) || (($document['transport_mode_type_id'] === '01' && !$document['is_transport_m1l']) && ($document['transport_tuc']  && $document['has_transport_driver_01'] ==true)))
                <cac:TransportHandlingUnit>
                    <cac:TransportEquipment>
                        <!-- VEHICULO PRINCIPAL -->
                        <!-- PLACA - VEHICULO PRINCIPAL -->
                        <cbc:ID>{{ $document['transport_plate_number'] }}</cbc:ID>
                         <!-- VEHICULOS SECUNDARIOS -->
                        @if($document['secondary_transports'])
                            @foreach($document['secondary_transports'] as $row)
                            <cac:AttachedTransportEquipment>
                                <cbc:ID>{{ $row['plate_number'] }}</cbc:ID>
                            </cac:AttachedTransportEquipment>
                            @endforeach
                        @endif
                    </cac:TransportEquipment>
                </cac:TransportHandlingUnit>
        @endif
    </cac:Shipment>
    <!-- DETALLES DE BIENES A TRASLADAR -->
    @foreach($document['items'] as $row)
        <cac:DespatchLine>
            <!-- NUMERO DE ORDEN DEL ITEM -->
            <cbc:ID>{{ $loop->iteration }}</cbc:ID>
            <!-- CANTIDAD -->
            <cbc:DeliveredQuantity unitCode="{{ ($document['transfer_reason_type_id']=='09')?'U':$row['unit_type_id'] }}"
                                   unitCodeListAgencyName="United Nations Economic Commission for Europe"
                                   unitCodeListID="UN/ECE rec 20">{{ $row['quantity'] }}</cbc:DeliveredQuantity>
            <cac:OrderLineReference>
                <cbc:LineID>{{ $loop->iteration }}</cbc:LineID>
            </cac:OrderLineReference>
            <cac:Item>
                <cbc:Description><![CDATA[{{ $row['name'] }}]]></cbc:Description>
                <cac:SellersItemIdentification>
                    <cbc:ID><![CDATA[{{ $row['internal_id'] }}]]></cbc:ID>
                </cac:SellersItemIdentification>
            </cac:Item>
        </cac:DespatchLine>
    @endforeach
</DespatchAdvice>

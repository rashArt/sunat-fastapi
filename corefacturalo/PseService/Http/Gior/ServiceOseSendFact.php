<?php

namespace Modules\PseService\Http\Gior;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\CoreFacturalo\WS\Response\BillResult;
use Modules\PseService\Http\Gior\Endpoints;
use Modules\PseService\Http\Gior\Errors;
use App\Models\Tenant\Company;
use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\Log;


/**
 * Class Service.
 */
final class ServiceOseSendFact
{
    private function company()
    {
        $company = Company::first();
        return $company;
    }

    /*
     * se consulta el api o el cache dependiendo del caso
     * se puede forzar un nuevo token en caso de que haga falta con el parametro force
     * si hay errores se devuelve lo que haya retornado el api con mensajes de su documentacion
     */
    public function getToken()
    {
        $queryToken = $this->queryToken();
        if (!$queryToken['success']) {
            throw new Exception("OSE - token. Code: {$queryToken['code']}; Description:  {$queryToken['error']}");
        }
        $token = $queryToken['token'];

        return [
            'success' => true,
            'token' => $token
        ];
    }

    private function queryToken()
    {
        $company = $this->company();
        $endpoints = $this->getEndpointsByProvider($company->pse_provider_id, $company->soap_type_id);
        $url = $endpoints['token'];

        $body = [
            'username' => $company->soap_username,
            'password' => $company->soap_password,
        ];


        $client = new Client();
        $response = $client->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'verify' => false,
        ]);
        $statusCode = $response->getStatusCode();
        $data = json_decode($response->getBody(), true);
        $estado = $data['estado'] ?? $statusCode;

        if($statusCode !== 200) {
            return [
                'success' => false,
                'code' => $estado,
                'error' => Errors::getMessage($statusCode)
            ];
        }

        if($statusCode === 200) {
            return [
                'success' => true,
                'code' => $statusCode,
                'token' => $data['access_token'],
                'token_type' => $data['token_type'] ?? null,
                'expires_in' => $data['expires_in'] ?? null
            ];
        }
    }

    private function updateToken($token, $expire)
    {
        Cache::put('pse_token', $token, now()->addSeconds($expire));
    }

    public function sendXmlSigned($filename, $xmlSigned, $hasSummary = false)
    {
        $company = $this->company();
        $endpoints = $this->getEndpointsByProvider($company->pse_provider_id, $company->soap_type_id);
        $url = $endpoints['send'];
        $body = [
            'file_name' => $filename,
            'file_content' => base64_encode($xmlSigned)
        ];
        $token = $this->getToken();

        try {
            $client = new Client();
            $response = $client->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token['token'],
                    'Content-Type' => 'application/json',
                ],
                'verify' => false,  
                'body' => json_encode($body),
            ]);

            $statusCode = $response->getStatusCode();
            $data = json_decode($response->getBody(), true);

            if($statusCode == 200 || $statusCode ==202) {
                $estado = $data['estado'] ?? $statusCode;
                if($statusCode == $estado) {
                    $mensaje = $this->validateObject($data, 'message');
                    $errors = $this->validateObject($data, 'errors');

                    if($hasSummary) {
                        $ticket = $this->validateObject($data, 'ticket');
                        return [
                            'success' => true,
                            'code' => $estado,
                            //'ticket' => $ticket,
                            'message' => $mensaje ?? null,
                            'observations' => $data['observations'] ?? null,
                            'xml_signed' => $data['xml'] ?? null,
                            'cdr' => $this->validateObject($data, 'cdr') ?? null,
                            'errors' => $errors ?? null,
                        ];
                    }
                    // tomar en cuenta tipos de documento
                    // ticket para envios que requieren 2 pasos
                    $observaciones = $this->validateObject($data, 'observations');
                    $cdr = $this->validateObject($data, 'cdr');
                    return [
                        'success' => true,
                        'code' => $estado,
                        'message' => $mensaje ?? null,
                        'observations' => $observaciones ?? null,
                        'xml_signed' => $data['xml'] ?? null,
                        'cdr' => $cdr ?? null,
                        'errors' => $errors ?? null,
                    ];
                }
            }
        } catch (\GuzzleHttp\Exception\RequestException $th) {
            if ($th->hasResponse()) {
                $body = $th->getResponse()->getBody()->getContents();
                $error = json_decode($body, true);
                $entity_validator = isset($error['is_rejected']) ?? 'SUNAT' ;
                $description = $entity_validator . implode(', ', $error['errors'] ?? []);
                //$parser_error = $this->parserCodeErrors($error['errors'][0]);
                Log::info('OSE SEND XML SIGNED ERROR', [
                    'error' => $error,
                ]);
                return [
                    'success' => false,
                    'message' => $description,
                    'errors' => $error['errors'] ?? null,
                    'is_rejected' => $error['is_rejected'] ?? false,
                    'code' => $error['status'] ?? null,
                ];
            }

        }
    }

    /*
     * valida key en el array de respuesta
     */
    private function validateObject($response, $key)
    {
        return isset($response[$key]) ? $response[$key] : null;
    }

    public function getCdrResponse($cdr_b64)
    {
        $cdr_xml = base64_decode($cdr_b64);

        $dom = new \DOMDocument();
        $dom->loadXML($cdr_xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('x', $dom->documentElement->namespaceURI);

        $responseCodeNode = $xpath->query('/x:ApplicationResponse/cac:DocumentResponse/cac:Response/cbc:ResponseCode')->item(0);
        $responseCode = $responseCodeNode ? $responseCodeNode->nodeValue : null;

        $descriptionNode = $xpath->query('/x:ApplicationResponse/cac:DocumentResponse/cac:Response/cbc:Description')->item(0);
        $description = $descriptionNode ? $descriptionNode->nodeValue : null;

        $nodes = $xpath->query('/x:ApplicationResponse/cbc:Note');
        $notes = [];
        if ($nodes->length > 0) {
            foreach ($nodes as $node) {
                $notes[] = $node->nodeValue;
            }
        }

        $response = [
            'code' => $responseCode,
            'description' => 'OSE - '.$description,
            'notes' => $notes,
        ];

        // Retornar el array
        return $response;
    }

    public function querySummary($filename)
    {
        try {
            $company = $this->company();
            $endpoints = $this->getEndpointsByProvider($company->pse_provider_id, $company->soap_type_id);
            $url = str_replace('{file_name}', $filename, $endpoints['query']);
            $token = $this->getToken();

            $client = new Client();
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token['token'],
                    'Content-Type' => 'application/json',
                ],
                'verify' => false,
            ]);

            $statusCode = $response->getStatusCode();
            $data = json_decode($response->getBody(), true);

            if($statusCode !== 200) {
                // dd($body);
                // dd($response->object());
                $error = Errors::getMessage($statusCode);
                $errors = $this->validateObject($data, 'errors');
                return [
                    'success' => false,
                    'message' => $error . ' | Detalles: '.json_encode($errors),
                    'code' => $statusCode,
                ];
            }

            if($statusCode == 200) {
                    $code = 0;
                    $mensaje = $this->validateObject($data, 'message');
                    $rechazado = $this->validateObject($data, 'is_rejected');
                    $errors = $this->validateObject($data, 'error_detail');
                    $observaciones = $this->validateObject($data, 'observations');
                    $document_status = $this->validateObject($data, 'document_status');
                    $cdr = $this->validateObject($data, 'cdr');
                    if ($errors) {
                        $parser_error =  $this->parserCodeErrors($errors);
                        $code = $parser_error['code'];
                    }
                    return [
                        'success' => true,
                        'code' => $statusCode,
                        'message' => $mensaje,
                        'observations' => $observaciones,
                        'xml_signed' => $data['xml'] ?? null,
                        'cdr' => $cdr,
                        'code' => $code,
                        'document_status' => $document_status,
                        'rejected' => $rechazado,
                        'errors' => $errors,
                    ];
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            
            $response = $e->getResponse();
            $data = json_decode($response->getBody(), true);
            $rechazado = $this->validateObject($data, 'is_rejected');
            return [
                'success' => true,
                'message' => 'Error en consulta: ' . json_encode($data['errors']),
                'status' => $data['status'],
                'is_rejected' => $rechazado,
                'cdr' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Excepción: ' . $e->getMessage(),
                'code' => 500,
            ];
        }
    }
    public function getEndpointsByProvider($providerId, $soapTypeId)
    {
        // Ignora providerId, solo usa SendFact
        return [
            'token'    => $soapTypeId == 2 ? Endpoints::SENDF_TOKEN : Endpoints::SENDF_BETA_TOKEN,
            'send'     => $soapTypeId == 2 ? Endpoints::SENDF_SEND : Endpoints::SENDF_BETA_SEND,
            'query'    => $soapTypeId == 2 ? Endpoints::SENDF_QUERY : Endpoints::SENDF_BETA_QUERY,
        ];
    }

    public function parserCodeErrors($error)
    {
        $parts = explode('-', $error, 2);
        return [
            'code' => is_integer((int)trim($parts[0])) ? (int) trim($parts[0]) : 0,
            'description' => $error,
        ];
    }

    public function validationCodeResponseIntegration($document_status, $message)
    {
        $code = (int)$document_status;
        $state = '';
        switch ($code) {
            case 0:
                $state = '09';
                break;
            case 1:
                $state = '05';
                break;
            case 2:
                $company = Company::active();
                if ($company->soap_send_id == 4) {
                    $state = '01';
                } else {
                    $state = '09';
                }
                break;
            case 4:
                $state = '03';
                break;
            case 5:
                $state = '01';
                Log::error("OSE ERROR: Code {$document_status}; Description: {$message}");
                break;
            default:
                $state = '05';
                break;
        }

        return $state;
    }

}
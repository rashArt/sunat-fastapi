<?php

namespace App\CoreFacturalo\WS\Services;

use App\CoreFacturalo\WS\Response\StatusCdrResult;

/**
 * Class ConsultCdrService.
 */
class ConsultCdrService extends BaseSunat
{
    /**
     * Obtiene el estado del comprobante.
     *
     * @param string $ruc
     * @param string $tipo
     * @param string $serie
     * @param int    $numero
     *
     * @return StatusCdrResult
     */
    public function getStatus($ruc, $tipo, $serie, $numero)
    {
        return $this->getStatusResult('getStatus', 'status', $ruc, $tipo, $serie, $numero);
    }

    /**
     * Obtiene el CDR del comprobante.
     *
     * @param string $ruc
     * @param string $tipo
     * @param string $serie
     * @param int    $numero
     *
     * @return StatusCdrResult
     */
    public function getStatusCdr($ruc, $tipo, $serie, $numero)
    {
        return $this->getStatusResult('getStatusCdr', 'statusCdr', $ruc, $tipo, $serie, $numero);
    }

    private function getStatusResult($method, $resultName, $ruc, $tipo, $serie, $numero)
    {
        $client = $this->getClient();
        $result = new StatusCdrResult();

        try {
            $params = [
                'rucComprobante' => $ruc,
                'tipoComprobante' => $tipo,
                'serieComprobante' => $serie,
                'numeroComprobante' => $numero,
            ];
            $response = $client->call($method, ['parameters' => $params]);
            $vars = get_object_vars($response);
            if (isset($vars['document']) && strlen($vars['document']) > 200) {
                $vars['document'] = '[ZIP: ' . strlen($vars['document']) . ' bytes]';
            }
            $document = $response->{$resultName} ?? null;

            if ($document) {
                if ($resultName === 'statusCdr') {
                    $document = $document->content;
                }
                $result->setCdrZip($document);
                $cdrResponse = $this->extractResponse($document);
                $code = $cdrResponse->getCode();
                $message = $cdrResponse->getDescription();

                $result->setCdrResponse($cdrResponse)
                    ->setCode($code)
                    ->setMessage($message)
                    ->setSuccess(true);

                if ($this->isExceptionCode($code)) {
                    $this->loadErrorByCode($result, $code);
                }
            } else {
                \Log::warning('No se recibió un documento válido desde el servicio SOAP');
            }

            // $statusCdr = $response->{$resultName};

            // $code = $statusCdr->statusCode;
            // $result->setCode($code)
            //     ->setMessage($statusCdr->statusMessage)
            //     ->setSuccess(true);

            // if (isset($statusCdr->content)) {
            //     $result->setCdrZip($statusCdr->content)
            //            ->setCdrResponse($this->extractResponse($statusCdr->content));
            //     $code = $result->getCdrResponse()->getCode();
            // }

            // if ($this->isExceptionCode($code)) {
            //     $this->loadErrorByCode($result, $code);
            // }
        } catch (\SoapFault $e) {
            $result->setError($this->getErrorFromFault($e));
        }

        return $result;
    }
}

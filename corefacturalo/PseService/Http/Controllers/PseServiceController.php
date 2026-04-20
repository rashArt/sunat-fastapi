<?php

namespace Modules\PseService\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PseService\Http\Gior\Endpoints as GiorEndpoints;
use Modules\PseService\Http\Gior\Errors as GiorErrors;
use Modules\PseService\Http\Gior\Service as GiorService;
use Modules\PseService\Http\Gior\ServiceSendFact as ServiceSendFact; 
use App\Models\Tenant\Company;

class PseServiceController extends Controller
{
    public function index()
    {
        $company = Company::first();
        if ($company->pse_provider_id == 4) {
            $service = new ServiceSendFact();
        } else {
            $service = new GiorService();
        }
        $endpoints = $service->getEndpointsByProvider($company->pse_provider_id, $company->soap_type_id);
        $getToken = $service->getToken();
        $res = [
            'endpoint' => $endpoints['token'],
            'error' => GiorErrors::getMessage('200'),
            'getToken' => $getToken,
            'validateSend' => $this->validateSend()
        ];

        // dd($res);
        return view('pseservice::index');
    }

    public function validateSend() {
        $company = Company::first();

        return $company->soap_send_id == 03 ? true : false;
    }
}

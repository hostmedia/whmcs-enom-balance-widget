<?php

/**
 * Name: WHMCS eNom Balance Widget
 * Description: This widget provides you with your eNom balance on your WHMCS admin dashboard.
 * Version 1.2.0
 * Created by Host Media Ltd
 * Website: https://www.hostmedia.co.uk/
 */

add_hook('AdminHomeWidgets', 1, function() {
    return new eNomBalanceWidget();
});

class eNomBalanceWidget extends \WHMCS\Module\AbstractWidget
{
    protected $title = 'eNom Balance';
    protected $description = 'Widget provides you with your eNom balance on your admin dashboard. Created by Host Media.';
    protected $weight = 150;
    protected $columns = 1;
    protected $cache = true;
    protected $cacheExpiry = 120;

    public function getData()
    {
        // Config
        $enomusername = 'YOUR-ENOM-USERNAME-HERE';
        $enompassword = 'YOUR-ENOM-PASSWORD-HERE';

        // Timeout configuration (in seconds)
        $connect_timeout = 5;  // Max time to establish connection
        $request_timeout = 10; // Max total time for the request
        
        // URL
        // Live
        $enomapiurl = 'https://reseller.enom.com/';
        
        // Test
        // $enomapiurl = 'https://resellertest.enom.com/';
        
        // Curl
        $enomapiurl .= 'interface.asp?command=GetBalance&uid='.$enomusername.'&pw='.$enompassword.'&responsetype=xml';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $enomapiurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $request_timeout);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);

        $result = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Handle cURL errors (timeout, connection refused, etc.)
        if ($result === false || !empty($curl_error)) {
            return [
                'enom' => (object)[
                    'ErrCount' => 1,
                    'errors' => (object)['Err1' => 'API unavailable: ' . ($curl_error ?: 'Connection failed')]
                ],
                'balance' => null,
                'availableBalance' => null,
            ];
        }

        // Handle HTTP errors
        if ($http_code >= 400) {
            return [
                'enom' => (object)[
                    'ErrCount' => 1,
                    'errors' => (object)['Err1' => 'API returned HTTP ' . $http_code]
                ],
                'balance' => null,
                'availableBalance' => null,
            ];
        }

        // Handle XML parsing errors
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($result);
        if ($xml === false) {
            return [
                'enom' => (object)[
                    'ErrCount' => 1,
                    'errors' => (object)['Err1' => 'Invalid API response (XML parse error)']
                ],
                'balance' => null,
                'availableBalance' => null,
            ];
        }

        $json = json_encode($xml);
        $data = json_decode($json);
        
        $dataArray = array(
            'enom'  => $data
            , 'balance' => $data->Balance ?? null
            , 'availableBalance' => $data->AvailableBalance ?? null
        );
        
        return $dataArray;
    }

    public function generateOutput($data)
    {

        if ($data['enom']->ErrCount > 0) {
            
return <<<EOF
    <div class="widget-content-padded">
        <strong>There was an error:</strong><br/>
        {$data['enom']->errors->Err1}
    </div>
EOF;
        }
        
        return <<<EOF
    <div class="widget-content-padded">
        <div class="row text-center">
            <div class="col-sm-6">
                <h4><strong>&#36;{$data['balance']}</strong></h4>
                Balance
            </div>
            <div class="col-sm-6">
                <h4><strong>&#36;{$data['availableBalance']}</strong></h4>
                Available Balance
            </div>
        </div>
        <div class="row text-center" style="margin-top: 20px;">
            <a href="https://www.enom.com/myaccount/RefillAccount.aspx" class="btn btn-default btn-sm" target="_blank"><i class="fas fa-credit-card fa-fw"></i> Refill Account</a>
        </div>
    </div>
EOF;
    }
}

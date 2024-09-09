<?php

namespace EveryRESTfulAPI\Extension;

use SilverStripe\ORM\DataExtension;

class EveryRESTfulAPIMemberExtension extends DataExtension {
    private static $db = array(
        'RESTFulToken' => 'Varchar(100)',
        'RESTFulTokenExpire' => 'Int'
    );
    
     private static $indexes = [
        'APIMemberIndex' => ['RESTFulToken','RESTFulTokenExpire']
    ];
}

?>
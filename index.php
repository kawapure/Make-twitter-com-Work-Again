<?php
include "includes/autoload.php";

use Rehike\SimpleFunnel;
use Rehike\SimpleFunnelResponse;

SimpleFunnel::funnelCurrentPage()->then(function (SimpleFunnelResponse $result)
{
    $result->output();
});
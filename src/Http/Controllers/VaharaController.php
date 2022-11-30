<?php

namespace Vaharadev\LaravelClient\Http\Controllers;

use GuzzleHttp\Exception\GuzzleException;
use Vaharadev\LaravelClient\Util\VaharaUtil;
use Illuminate\Http\Request;

class VaharaController extends Controller
{
    public function publish(Request $request)
    {
        $vaharaUtil = new VaharaUtil();
        $itemId = $request->input('id');

        try {
            $vaharaUtil->updateData($itemId);
            $vaharaUtil->updateRelationalData();

            return (['status' => 'ok']);

        } catch (GuzzleException $e) {}

        return (['status' => 'bad']);
    }
}

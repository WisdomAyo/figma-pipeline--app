<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FigmaAuthController extends Controller
{
    /**
     * Redirects user to Figma login/consent page.
     */
    public function redirectToFigma()
    {
        $clientId = config('figma.api.client_id');
        $redirectUri = config('figma.api.redirect_uri');
        $scope = 'file_variables:read';

        $authUrl = "https://www.figma.com/oauth?client_id={$clientId}"
            . "&redirect_uri=" . urlencode($redirectUri)
            . "&scope={$scope}"
            . "&state=xyz123"
            . "&response_type=code";

        return redirect()->away($authUrl);
    }

    /**
     * Handles callback from Figma OAuth.
     */
    public function handleCallback(Request $request)
    {
        if ($request->has('error')) {
            return response()->json(['error' => $request->get('error')], 400);
        }

        $code = $request->get('code');
        $client = new Client();

        $response = $client->post('https://www.figma.com/api/oauth/token', [
            'form_params' => [
                'client_id'     => config('figma.api.client_id'),
                'client_secret' => config('figma.api.client_secret'),
                'redirect_uri'  => config('figma.api.redirect_uri'),
                'code'          => $code,
                'grant_type'    => 'authorization_code',
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        // Store tokens securely (example: Cache for demo, DB for production)
        Cache::put('figma_access_token', $data['access_token'], now()->addSeconds($data['expires_in']));
        Cache::put('figma_refresh_token', $data['refresh_token']);

        return response()->json([
            'success' => true,
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_in' => $data['expires_in'],
        ]);
    }
}

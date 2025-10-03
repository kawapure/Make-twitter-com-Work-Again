<?php
use YukisCoffee\CoffeeRequest\Attributes\Override;
use YukisCoffee\CoffeeRequest\Enum\NetworkResult;
use YukisCoffee\CoffeeRequest\Handler\NetworkHandler;
use YukisCoffee\CoffeeRequest\Network\Request;
use YukisCoffee\CoffeeRequest\Network\Response;

const CURL_BIN_PATH = "bin/curl.exe";

class CurlExternalHandler extends NetworkHandler
{
    /** 
     * Stores all active requests.
     * 
     * @var RequestWrapper[] 
     */
    private array $requests = [];

    #[Override]
    public function addRequest(Request $request): void
    {
        $this->requests[] = $this->convertRequest($request);
    }

    #[Override]
    public function clearRequests(): void
    {
        $this->requests = [];
    }

    /**
     * Convert a Request to a cURL handle.
     */
    protected function convertRequest(Request $request): RequestWrapper
    {
        return RequestTransformer::convert($request);
    }

    /**
     * Convert a cURL response to a CoffeeRequest Response object.
     */
    protected function makeResponse(
            int $curlCode,
            int $status,
            string $raw,
            RequestWrapper $wrapper,
    ): Response
    {
        $result = new Response(
            $wrapper->instance,
            $status,
            $raw,
            $wrapper->responseHeaders
        );
        $result->resultCode = $this->makeResultCode($curlCode);
        return $result;
    }

    /**
     * Convert a cURL status code to a NetworkResult code.
     */
    protected function makeResultCode(int $curlCode): int
    {
        switch ($curlCode)
        {
            case 0: // CURLE_OK
                return NetworkResult::SUCCESS;
            case 3: // CURL_URL_MALFORMAT
                return NetworkResult::E_MALFORMED_URL;
            case 5: // CURL_COULDNT_RESOLVE_PROXY
                return NetworkResult::E_COULDNT_RESOLVE_PROXY;
            case 6: // CURL_COULDNT_RESOLVE_HOST
                return NetworkResult::E_COULDNT_RESOLVE_HOST;
            case 7: // CURL_COULDNT_CONNECT
                return NetworkResult::E_COULDNT_CONNECT;
        }

        return NetworkResult::E_FAILED;
    }

    /**
     * Resolve a Request with a Response.
     */
    protected function sendResponse(Request $request, Response $response): void
    {
        $request->resolve($response);
    }
}
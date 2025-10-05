<?php
namespace TwitterRedirect\CurlExternal;

use Exception;
use Generator;
use YukisCoffee\CoffeeRequest\Attributes\Override;
use YukisCoffee\CoffeeRequest\Enum\NetworkResult;
use YukisCoffee\CoffeeRequest\Handler\NetworkHandler;
use YukisCoffee\CoffeeRequest\Network\Request;
use YukisCoffee\CoffeeRequest\Network\Response;

/**
 * @author Isabella Lulamoon <kawapure@gmail.com>
 */
class CurlExternalHandler extends NetworkHandler
{
    private const USE_SYNCHRONOUS_REQUEST =
        // PHP on Windows sucks. https://www.php.net/manual/en/function.proc-open.php#128252
        PHP_OS == "WINNT";
    
    private const CHUNK_SIZE = 16;
    
    /** 
     * Stores all active requests.
     * 
     * @var CurlExternalRequest[] 
     */
    private array $requests = [];
    
    private string $curlBinaryPath;
    
    public function __construct(string $curlBinaryPath)
    {
        $this->curlBinaryPath = $curlBinaryPath;
    }
    
    #[Override]
    public function onRun(): Generator
    {
        if (count($this->requests) == 0)
        {
            $this->fulfill();
            return;
        }
        
        // Set up all requests:
        foreach ($this->requests as $request)
        {
            $request->hProcess = proc_open(
                $this->curlBinaryPath . " " . implode(" ", $request->args),
                $request->pipeDescriptor,
                $request->pipes,
            );
            
            // We don't want any pipe to block.
            foreach ($request->pipes as $pipe)
                stream_set_blocking($pipe, false);
            
            // We currently don't use stdin at all, so we'll just close the pipe.
            fclose($request->pipes[CurlExternalRequest::PIPE_STDIN]);
        }
        
        $remainingRequests = count($this->requests);
        
        // Get content from each request:
        while ($remainingRequests > 0)
        {
            foreach ($this->requests as $request)
            {
                if (!is_resource($request->hProcess))
                {
                    continue;
                }
                
                usleep(10);
                
                if (!self::USE_SYNCHRONOUS_REQUEST)
                {
                    // Read a little bit from stdout and stderr:
                    $stdoutChunk = $this->readPipeChunk($request, CurlExternalRequest::PIPE_STDOUT);
                    $stderrChunk = $this->readPipeChunk($request, CurlExternalRequest::PIPE_STDERR);
                    
                    if ($stdoutChunk)
                    {
                        $request->stdout .= $stdoutChunk;
                    }
                    
                    if ($stderrChunk)
                    {
                        $request->stderr .= $stderrChunk;
                    }
                }
                else
                {
                    $request->stdout = stream_get_contents($request->pipes[CurlExternalRequest::PIPE_STDOUT]);
                    $request->stderr = stream_get_contents($request->pipes[CurlExternalRequest::PIPE_STDERR]);
                }
                
                if (self::USE_SYNCHRONOUS_REQUEST || !$stdoutChunk && !$stderrChunk)
                {
                    // The request is done.
                    $response = $this->finishRequest($request);
                    $this->sendResponse($request->request, $response);
                    $remainingRequests--;
                }
                
                yield;
            }
        }
        
        $this->fulfill();
    }
    
    private function isPipeOpen(CurlExternalRequest $request, int $pipe): bool
    {
        return $request->pipes[$pipe] != null;
    }
    
    private function readPipeChunk(
        CurlExternalRequest $request,
        int $pipe,
        int $chunkSize = self::CHUNK_SIZE,
    ): ?string
    {
        if ($this->isPipeOpen($request, $pipe))
        {
            $nextChunkStdout = fread($request->pipes[$pipe], $chunkSize);
            if ($nextChunkStdout === false || feof($request->pipes[$pipe]))
            {
                $this->closePipe($request, $pipe);
            }
            
            return $nextChunkStdout;
        }
        
        return null;
    }
    
    private function closePipe(CurlExternalRequest $request, int $pipe)
    {
        fclose($request->pipes[$pipe]);
        $request->pipes[$pipe] = null;
    }

    #[Override]
    public function addRequest(Request $request): void
    {
        $this->requests[] = CurlExternalRequest::from($request);
    }

    #[Override]
    public function clearRequests(): void
    {
        $this->requests = [];
    }

    /**
     * Convert a cURL response to a CoffeeRequest Response object.
     */
    protected function finishRequest(CurlExternalRequest $request): Response
    {
        $curlCode = proc_close($request->hProcess);
        $request->hProcess = null;
        
        $responseText = $request->stdout;
        
        // Content is separated from headers by a blank line. We'll find this
        // terminator first.
        $terminator = strpos($responseText, "\r\n\r\n");
        
        if ($terminator === false)
        {
            $terminator = strpos($responseText, "\n\n");
            $terminator += strlen("\n\n");
        }
        else
        {
            $terminator += strlen("\r\n\r\n");
        }
        
        if ($terminator === false)
        {
            throw new Exception("Failed to find response terminator. " . $responseText);
        }
        
        $headersText = substr($responseText, 0, $terminator);
        
        $headers = explode("\n", $headersText);
        $status = 0;
        
        $parsedHeaders = [];
        
        foreach ($headers as $i => $header)
        {
            $header = rtrim($header, "\r");
            
            // HTTP version and response code:
            if ($i == 0 && str_starts_with($header, "HTTP/"))
            {
                $status = $this->parseHttpHeader($header);
            }
            
            $parts = explode(":", $header);
            if (count($parts) == 2)
            {
                $parsedHeaders += [ strtolower($parts[0]) => ltrim($parts[1], " ") ];
            }
        }
        
        $htmlText = substr($responseText, $terminator);
        
        $result = new Response(
            $request->request,
            $status,
            $htmlText,
            $parsedHeaders,
        );
        
        $result->resultCode = $this->makeResultCode($curlCode);
        return $result;
    }
    
    /**
     * Parses a HTTP header and gets the response status from it.
     * 
     * For example: "HTTP/2 200 OK"
     */
    private function parseHttpHeader(string $header): int
    {
        $parts = explode(" ", $header);
        return (int)@$parts[1] ?? 0;
    }

    /**
     * Convert a cURL status code to a NetworkResult code.
     */
    protected function makeResultCode(int $curlCode): int
    {
        switch ($curlCode) {
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

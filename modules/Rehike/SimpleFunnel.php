<?php
namespace Rehike;

use YukisCoffee\CoffeeRequest\CoffeeRequest;
use YukisCoffee\CoffeeRequest\Promise;
use YukisCoffee\CoffeeRequest\Network\Request;
use YukisCoffee\CoffeeRequest\Network\Response;
use YukisCoffee\CoffeeRequest\Network\ResponseHeaders;

/**
 * A simple tool to funnel requests from a certain domain, while ignoring any
 * proxies active
 * 
 * @author Aubrey Pankow <aubyomori@gmail.com>
 * @author Taniko Yamamoto <kirasicecreamm@gmail.com>
 */
class SimpleFunnel
{
    /**
     * Remove these request headers.
     * LOWERCASE ONLY
     * 
     * @var string[]
     */
    public static $illegalRequestHeaders = [
        "accept",
        "accept-encoding",
        "host",
        //"origin",
        //"referer"
    ];

    /**
     * Remove these response headers.
     * LOWERCASE ONLY
     * 
     * @internal
     * @var string[]
     */
    public static $illegalResponseHeaders = [
        "content-encoding",
        "content-length",
        "transfer-encoding" // broke linux for months lol
    ];

    /**
     * Funnel a response through.
     * 
     * @param array $opts  Options such as headers and request method
     * @return Promise<SimpleFunnelResponse>
     */
    public static function funnel(array $opts): Promise/*<SimpleFunnelResponse>*/
    {
        // Required fields
        if (!isset($opts["host"])) 
            self::error("No hostname specified");

        if (!isset($opts["uri"]))
            self::error("No URI specified");

        // Default options
        $opts += [
            "method" => "GET",
            "useragent" => "SimpleFunnel/1.0",
            "body" => "",
            "headers" => []
        ];

        $headers = [];

        foreach ($opts["headers"] as $key => $val)
        {
            if (!in_array(strtolower($key), self::$illegalRequestHeaders))
            {
                if (strtolower($key) == "origin")
                {
                    $headers[$key] = "https://x.com";
                }
                else if (strtolower($key) == "referer")
                {
                    $headers[$key] = "https://x.com/" .
                        explode("https://twitter.com/", $val)[1];
                }
                else
                {
                    $headers[$key] = $val;
                }
            }
        }
        
        $headers["TE"] = "trailers";
        
        $headers["Host"] = $opts["host"];

        // Set up cURL and perform the request
        $url = "https://" . $opts["host"] . $opts["uri"];

        // Set up the request.
        $params = [
            "method" => $opts["method"],
            "headers" => $headers,
            "redirect" => "manual",
        ];

        if ("POST" == $params["method"])
        {
            $params["body"] = $opts["body"];
        }

        $wrappedResponse = new Promise/*<Response>*/;
        
        if ($opts["uri"] == "/i/api/graphql/ZSBCfCefJFumbPcLcwR64Q/CreateTweet")
        {
            $headers2 = [];
            foreach ($headers as $k => $h)
                $headers2[] = "$k: $h";
            
            $curl = new CurlImpersonate();
            $curl->setopt(CURLCMDOPT_URL, $url);
            $curl->setopt(CURLCMDOPT_METHOD, $opts["method"]);
            $curl->setopt(CURLCMDOPT_HEADER, true);
            $curl->setopt(CURLCMDOPT_POSTFIELDS, $opts["body"]);
            $curl->setopt(CURLCMDOPT_HTTP_HEADERS, $headers2);
            $curl->setopt(CURLCMDOPT_ENGINE, $_SERVER["DOCUMENT_ROOT"] . "/bin/curl_firefox133.bat");
            $response = $curl->execStandard();
            echo $response;
            $curl->closeStream();
            exit();
        }
        
        $request = CoffeeRequest::request($url, $params);
        
        // header("access-control-allow-credentials: true");
        // header("access-control-allow-origin: https://twitter.com");
        // header("access-control-allow-methods: HEAD,PUT,GET,POST,DELETE");
        // header("access-control-allow-headers: X-Attest-Token,X-Web-Auth-Multi-User-Id,Timezone,X-Contributor-Version,X-Twitter-CESModel-Version,Server,X-Twitter-Client-Version,X-Twitter-Diffy-Request-Key,Dtab-Local,X-Twitter-Client-Language,X-Client-Transaction-Id,X-XP-Auth-Token,Apollo-Require-Preflight,If-Modified-Since,X-Twitter-Client,SecurelyOktaToken,X-XP-Forwarded-With,X-TD-Iff-Mtime,X-Client-UUID,X-Twitter-Auth-Type,Content-Length,Alt-Used,X-B3-Flags,Cache-Control,X-Transaction-Id,X-XP-TX-Token,X-TFE-Bot-Test,Content-Type,X-TD-Mtime-Check,Pragma,X-CSRF-Token,X-Twitter-Polling,X-Twitter-Active-User,X-Guest-Token,LivePipeline-Session,X-Twitter-UTCOffset,X-Response-Time,X-Act-As-User-Id,X-XP-Forwarded-For,Authorization,X-Xai-Request-Id,X-XP-IDV-Token,X-Contribute-To-User-Id,X-Attest-Signature");
        // header("access-control-expose-headers: X-Twitter-Spotify-Access-Token,X-Twitter-Client-Version,X-Twitter-Diffy-Request-Key,X-Rate-Limit-Limit,X-TD-Mtime,X-Twitter-Client,Backoff-Policy,X-Rate-Limit-Remaining,Content-Length,X-Rate-Limit-Reset,X-Transaction-Id,X-Acted-As-User-Id,X-Twitter-Polling,X-Twitter-UTCOffset,X-Response-Time");
        // header("access-control-max-age: 1728000");
        // var_dump($headers);
        // var_dump($request);
        // die();

        $request->then(function($response) use ($wrappedResponse) {
            $wrappedResponse->resolve(SimpleFunnelResponse::fromResponse($response));
        });

        CoffeeRequest::run();

        return $wrappedResponse;
    }

    /**
     * Convert a list of response headers to HTTP-compatible ones.
     */
    public static function responseHeadersToHttp(
            ResponseHeaders $headers, 
            bool $ignoreIllegal = true
    ): array
    {
        $out = [];

        foreach ($headers as $name => $value)
        {
            if (is_array($value))
            {
                foreach ($value as $childValue)
                {
                    $out[] = $name . ": " . $childValue;
                }
            }
            else
            {
                $out[] = $name . ": " . $value;
            }
        }

        return $out;
    }

    /**
     * Output an error.
     */
    private static function error(string $message): void
    {
        http_response_code(500);
        echo("
        <title>SimpleFunnel Error</title>
        <style>body>*{margin:8px 0}</style>
        <h2>An error has occured in SimpleFunnel</h2>
        <p><b>Error</b>: " . $message . "</p>
        <small><i>Please report this to the GitHub.</i></small>
        ");
        return;
    }
    
    /**
     * Funnel a page with the current data.
     * 
     * @return Promise<SimpleFunnelResponse>
     */
    public static function funnelCurrentPage(): Promise/*<SimpleFunnelResponse>*/
    {
        return self::funnel([
            "method" => $_SERVER["REQUEST_METHOD"],
            "host" => $_SERVER["HTTP_HOST"] == "api.twitter.com"
                ? "api.x.com"
                : "x.com",
            "uri" => $_SERVER["REQUEST_URI"],
            "useragent" => $_SERVER["HTTP_USER_AGENT"],
            "body" => file_get_contents("php://input"),
            "headers" => getallheaders()
        ]);
    }
}

/**
 * A custom class that represents a SimpleFunnel response.
 * 
 * @author Taniko Yamamoto <kirasicecreamm@gmail.com>
 */
class SimpleFunnelResponse extends Response
{
    public static function fromResponse(Response $response): self
    {
        return new self(
            source: $response->sourceRequest,
            status: 200/*$response->status*/,
            content: $response->getText(),
            headers: self::processResponseHeaders($response->headers)
        );
    }

    /**
     * Output the response of the page.
     */
    public function output(): void
    {
        http_response_code($this->status);

        foreach (SimpleFunnel::responseHeadersToHttp($this->headers) as $httpHeader)
        {
            header($httpHeader, false);
        }
        
        echo($this->getText());
        exit();
    }

    /**
     * Process the response headers and remove illegal headers.
     */
    private static function processResponseHeaders(ResponseHeaders $headers): array
    {
        $result = [];

        foreach ($headers as $name => $value)
        {
            if (!in_array(strtolower($name), SimpleFunnel::$illegalResponseHeaders))
            {
                $result[$name] = $value;
            }
        }
        
        // All of the below are required for access control:
        //$result["access-control-allow-credentials"] = "true";
        //$result["access-control-allow-origin"] = "https://twitter.com";
        //$result["access-control-allow-headers"] = "X-Attest-Token,X-Web-Auth-Multi-User-Id,Timezone,X-Contributor-Version,X-Twitter-CESModel-Version,Server,X-Twitter-Client-Version,X-Twitter-Diffy-Request-Key,Dtab-Local,X-Twitter-Client-Language,X-Client-Transaction-Id,X-XP-Auth-Token,Apollo-Require-Preflight,If-Modified-Since,X-Twitter-Client,SecurelyOktaToken,X-XP-Forwarded-With,X-TD-Iff-Mtime,X-Client-UUID,X-Twitter-Auth-Type,Content-Length,Alt-Used,X-B3-Flags,Cache-Control,X-Transaction-Id,X-XP-TX-Token,X-TFE-Bot-Test,Content-Type,X-TD-Mtime-Check,Pragma,X-CSRF-Token,X-Twitter-Polling,X-Twitter-Active-User,X-Guest-Token,LivePipeline-Session,X-Twitter-UTCOffset,X-Response-Time,X-Act-As-User-Id,X-XP-Forwarded-For,Authorization,X-Xai-Request-Id,X-XP-IDV-Token,X-Contribute-To-User-Id,X-Attest-Signature";
        
        // $result["access-control-allow-methods"] = "HEAD,PUT,GET,POST,DELETE";
        // $result["access-control-expose-headers"] = "X-Twitter-Spotify-Access-Token,X-Twitter-Client-Version,X-Twitter-Diffy-Request-Key,X-Rate-Limit-Limit,X-TD-Mtime,X-Twitter-Client,Backoff-Policy,X-Rate-Limit-Remaining,Content-Length,X-Rate-Limit-Reset,X-Transaction-Id,X-Acted-As-User-Id,X-Twitter-Polling,X-Twitter-UTCOffset,X-Response-Time";
        // $result["access-control-max-age"] = "1728000";

        return $result;
    }
}
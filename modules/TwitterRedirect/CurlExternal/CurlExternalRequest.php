<?php
namespace TwitterRedirect\CurlExternal;

use YukisCoffee\CoffeeRequest\Enum\RedirectPolicy;
use YukisCoffee\CoffeeRequest\Handler\Curl\RequestWrapper;
use YukisCoffee\CoffeeRequest\Network\Request;

/**
 * @author Isabella Lulamoon <kawapure@gmail.com>
 */
final class CurlExternalRequest
{
    public const PIPE_STDIN  = 0;
    public const PIPE_STDOUT = 1;
    public const PIPE_STDERR = 2;
    
    /**
     * A reference to the original request.
     */
    public Request $request;
    
    /**
     * @var resource Handle to the process.
     */
    public $hProcess;
    
    /**
     * Descriptor for pipes for the process to be opened with.
     */
    public array $pipeDescriptor = [
        self::PIPE_STDIN  => ["pipe", "r"],
        self::PIPE_STDOUT => ["pipe", "w"],
        self::PIPE_STDERR => ["pipe", "w"],
    ];
    
    /**
     * An array of pipe handles.
     */
    public array $pipes = [];
    
    /**
     * Contains the retrieved content of the webpage.
     */
    public string $stdout = "";
    
    /**
     * Contains other cURL messages.
     */
    public string $stderr = "";
    
    /**
     * Contains the HTTP status of the response after the result concludes.
     */
    public int $httpStatus = 0;
    
    public array $args = [
        // This argument is always passed, as it's required to resolve SSL certificates.
        "--ca-native",
    ];
    
    private function __construct() {}
    
    public static function from(Request $request): self
    {
        $instance = new self();
        $instance->setupFromRequest($request);
        return $instance;
    }
    
    private function setupFromRequest(Request $request): void
    {
        $this->request = $request;
        
        // Request method:
        // -X is shorthand for --request
        $this->passArgument("-X " . Utils::escapeCommandLine($request->method));
        
        // POST data:
        if ($request->method == "POST" && isset($request->body))
        {
            // -d is shorthand for --data
            $this->passArgument("-d " . Utils::ensureQuoted(Utils::escapeCommandLine($request->body)));
        }
        
        // Request headers:
        foreach ($request->headers as $key => $value)
        {
            // -H is shorthand for --header
            $this->passArgument("-H " . Utils::ensureQuoted(Utils::escapeCommandLine(
                "$key: $value"
            )));
        }
        
        if (isset($request->redirectPolicy) && $request->redirectPolicy == RedirectPolicy::FOLLOW)
        {
            $this->passArgument("-L"); // Shorthand for --location
        }
        
        // We will always include headers in the response so that they can be
        // reported back to the client.
        $this->passArgument("-i"); // Shorthand for --include
        
        // Pass the request URI:
        $this->passArgument(Utils::ensureQuoted(Utils::escapeCommandLine($request->url)));
    }
    
    public function passArgument(string $value): self
    {
        $this->args[] = $value;
        return $this;
    }
}
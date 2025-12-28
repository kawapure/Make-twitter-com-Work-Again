<?php
namespace TwitterRedirect\CurlExternal;

/**
 * @static
 */
final class Utils
{
    public static function escapeCommandLine(string $value): string
    {
        return PHP_OS == "WINNT"
            ? self::escape_win32_argv($value)
            : escapeshellarg($value);
    }
    
    public static function ensureQuoted(string $value): string
    {
        if (!str_starts_with($value, "\""))
        {
            return "\"$value\"";
        }
        
        return $value;
    }
    
    /**
     * Escape a single value in accordance with CommandLineToArgV()
     * https://docs.microsoft.com/en-us/previous-versions/17w5ykft(v=vs.85)
     */
    public static function escape_win32_argv(string $value): string
    {
        static $expr = '(
            [\x00-\x20\x7F"] # control chars, whitespace or double quote
            | \\\\++ (?=("|$)) # backslashes followed by a quote or at the end
            | \|
            | \<
            | \>
            )ux';

        if ($value === '') {
            return '""';
        }

        $quote = false;
        $replacer = function ($match) use ($value, &$quote) {
            switch ($match[0][0]) { // only inspect the first byte of the match
                case '"': // double quotes are escaped and must be quoted
                    $match[0] = '\\"';
                case ' ':
                case "\t": // spaces and tabs are ok but must be quoted
                    $quote = true;
                    return $match[0];
                case '\\': // matching backslashes are escaped if quoted
                    return $match[0] . $match[0];
                // This is the only way I could figure out how to escape a pipe.
                case "|":
                    return "\"|\"";
                case "<":
                    return "\"<\"";
                case ">":
                    return "\">\"";
                // TODO: Verify the necessity of this:
                // case "&":
                //     return "\"&\"";
                // case "^":
                //     return "\"^\"";
                default:
                    throw new \InvalidArgumentException(sprintf(
                        "Invalid byte at offset %d: 0x%02X",
                        strpos($value, $match[0]),
                        ord($match[0])
                    ));
            }
        };

        $escaped = preg_replace_callback($expr, $replacer, (string)$value);

        if ($escaped === null) {
            throw preg_last_error() === PREG_BAD_UTF8_ERROR
                ? new \InvalidArgumentException("Invalid UTF-8 string")
                : new \Error("PCRE error: " . preg_last_error());
        }

        return $quote // only quote when needed
            ? '"' . $escaped . '"'
            : $value;
    }
}
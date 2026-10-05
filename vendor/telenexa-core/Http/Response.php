<?php
namespace TeleNexa\Http;
class Response  {
    public $code;
    public $raw_body;
    public $body;
    public $headers;
    public function __construct($code, $raw_body, $headers, $json_args = array())  {
        $this->code = $code;
        $this->headers = $this->parseHeaders($headers);
        $this->raw_body = $raw_body;
        $this->body = $raw_body;
        array_unshift($json_args, $raw_body);
        if (function_exists('json_decode'))  {
            $json = call_user_func_array('json_decode', $json_args);
            if (json_last_error() === JSON_ERROR_NONE)  {
                $this->body = $json;
            }
        }
    }
    private function parseHeaders($raw_headers)  {
        if (function_exists('http_parse_headers'))  {
            return http_parse_headers($raw_headers);
        }
        else  {
            $key = '';
            $headers = array();
            foreach (explode("\n", $raw_headers) as $i => $h)  {
                $h = explode(':', $h, 2);
                if (isset($h[1]))  {
                    if (!isset($headers[$h[0]]))  {
                        $headers[$h[0]] = trim($h[1]);
                    }
                    elseif (is_array($headers[$h[0]]))  {
                        $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1])));
                    }
                    else  {
                        $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1])));
                    }
                    $key = $h[0];
                }
                else  {
                    if (substr($h[0], 0, 1) == "\t")  {
                        $headers[$key] .= "\r\n\t".trim($h[0]);
                    }
                    elseif (!$key)  {
                        $headers[0] = trim($h[0]);
                    }
                }
            }
            return $headers;
        }
    }
}

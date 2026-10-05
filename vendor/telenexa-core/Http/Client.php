<?php
namespace TeleNexa\Http;
class Client  {
    private static $cookie = null;
    private static $cookieFile = null;
    private static $curlOpts = array();
    private static $defaultHeaders = array();
    private static $handle = null;
    private static $jsonOpts = array();
    private static $socketTimeout = null;
    private static $verifyPeer = true;
    private static $verifyHost = true;
    private static $auth = array ( 'user' => '', 'pass' => '', 'method' => CURLAUTH_BASIC );
    private static $proxy = array( 'port' => false, 'tunnel' => false, 'address' => false, 'type' => CURLPROXY_HTTP, 'auth' => array ( 'user' => '', 'pass' => '', 'method' => CURLAUTH_BASIC ) );
    public static function jsonOpts($assoc = false, $depth = 512, $options = 0)  {
        return self::$jsonOpts = array($assoc, $depth, $options);
    }
    public static function verifyPeer($enabled)  {
        return self::$verifyPeer = $enabled;
    }
    public static function verifyHost($enabled)  {
        return self::$verifyHost = $enabled;
    }
    public static function timeout($seconds)  {
        return self::$socketTimeout = $seconds;
    }
    public static function defaultHeaders($headers)  {
        return self::$defaultHeaders = array_merge(self::$defaultHeaders, $headers);
    }
    public static function defaultHeader($name, $value)  {
        return self::$defaultHeaders[$name] = $value;
    }
    public static function clearDefaultHeaders()  {
        return self::$defaultHeaders = array();
    }
    public static function curlOpts($options)  {
        return self::mergeCurlOptions(self::$curlOpts, $options);
    }
    public static function curlOpt($name, $value)  {
        return self::$curlOpts[$name] = $value;
    }
    public static function clearCurlOpts()  {
        return self::$curlOpts = array();
    }
    public static function setMashapeKey($key)  {
        return self::defaultHeader('X-Mashape-Key', $key);
    }
    public static function cookie($cookie)  {
        self::$cookie = $cookie;
    }
    public static function cookieFile($cookieFile)  {
        self::$cookieFile = $cookieFile;
    }
    public static function auth($username = '', $password = '', $method = CURLAUTH_BASIC)  {
        self::$auth['user'] = $username;
        self::$auth['pass'] = $password;
        self::$auth['method'] = $method;
    }
    public static function proxy($address, $port = 1080, $type = CURLPROXY_HTTP, $tunnel = false)  {
        self::$proxy['type'] = $type;
        self::$proxy['port'] = $port;
        self::$proxy['tunnel'] = $tunnel;
        self::$proxy['address'] = $address;
    }
    public static function proxyAuth($username = '', $password = '', $method = CURLAUTH_BASIC)  {
        self::$proxy['auth']['user'] = $username;
        self::$proxy['auth']['pass'] = $password;
        self::$proxy['auth']['method'] = $method;
    }
    public static function get($url, $headers = array(), $parameters = null, $username = null, $password = null)  {
        return self::send(Method::GET, $url, $parameters, $headers, $username, $password);
    }
    public static function head($url, $headers = array(), $parameters = null, $username = null, $password = null)  {
        return self::send(Method::HEAD, $url, $parameters, $headers, $username, $password);
    }
    public static function options($url, $headers = array(), $parameters = null, $username = null, $password = null)  {
        return self::send(Method::OPTIONS, $url, $parameters, $headers, $username, $password);
    }
    public static function connect($url, $headers = array(), $parameters = null, $username = null, $password = null)  {
        return self::send(Method::CONNECT, $url, $parameters, $headers, $username, $password);
    }
    public static function post($url, $headers = array(), $body = null, $username = null, $password = null)  {
        return self::send(Method::POST, $url, $body, $headers, $username, $password);
    }
    public static function delete($url, $headers = array(), $body = null, $username = null, $password = null)  {
        return self::send(Method::DELETE, $url, $body, $headers, $username, $password);
    }
    public static function put($url, $headers = array(), $body = null, $username = null, $password = null)  {
        return self::send(Method::PUT, $url, $body, $headers, $username, $password);
    }
    public static function patch($url, $headers = array(), $body = null, $username = null, $password = null)  {
        return self::send(Method::PATCH, $url, $body, $headers, $username, $password);
    }
    public static function trace($url, $headers = array(), $body = null, $username = null, $password = null)  {
        return self::send(Method::TRACE, $url, $body, $headers, $username, $password);
    }
    public static function buildHTTPCurlQuery($data, $parent = false)  {
        $result = array();
        if (is_object($data))  {
            $data = get_object_vars($data);
        }
        foreach ($data as $key => $value)  {
            if ($parent)  {
                $new_key = sprintf('%s[%s]', $parent, $key);
            }
            else  {
                $new_key = $key;
            }
            if (!$value instanceof \CURLFile and (is_array($value) or is_object($value)))  {
                $result = array_merge($result, self::buildHTTPCurlQuery($value, $new_key));
            }
            else  {
                $result[$new_key] = $value;
            }
        }
        return $result;
    }
    public static function send($method, $url, $body = null, $headers = array(), $username = null, $password = null)  {
        self::$handle = curl_init();
        if ($method !== Method::GET)  {
            if ($method === Method::POST)  {
                curl_setopt(self::$handle, CURLOPT_POST, true);
            }
            else  {
                if ($method === Method::HEAD)  {
                    curl_setopt(self::$handle, CURLOPT_NOBODY, true);
                }
                curl_setopt(self::$handle, CURLOPT_CUSTOMREQUEST, $method);
            }
            curl_setopt(self::$handle, CURLOPT_POSTFIELDS, $body);
        }
        elseif (is_array($body))  {
            if (strpos($url, '?') !== false)  {
                $url .= '&';
            }
            else  {
                $url .= '?';
            }
            $url .= urldecode(http_build_query(self::buildHTTPCurlQuery($body)));
        }
        $curl_base_options = [ CURLOPT_URL => self::encodeUrl($url), CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 10, CURLOPT_HTTPHEADER => self::getFormattedHeaders($headers), CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => self::$verifyPeer, CURLOPT_SSL_VERIFYHOST => self::$verifyHost === false ? 0 : 2, CURLOPT_ENCODING => '' ];
        curl_setopt_array(self::$handle, self::mergeCurlOptions($curl_base_options, self::$curlOpts));
        if (self::$socketTimeout !== null)  {
            curl_setopt(self::$handle, CURLOPT_TIMEOUT, self::$socketTimeout);
        }
        if (self::$cookie)  {
            curl_setopt(self::$handle, CURLOPT_COOKIE, self::$cookie);
        }
        if (self::$cookieFile)  {
            curl_setopt(self::$handle, CURLOPT_COOKIEFILE, self::$cookieFile);
            curl_setopt(self::$handle, CURLOPT_COOKIEJAR, self::$cookieFile);
        }
        if (!empty($username))  {
            curl_setopt_array(self::$handle, array( CURLOPT_HTTPAUTH => CURLAUTH_BASIC, CURLOPT_USERPWD => $username . ':' . $password ));
        }
        if (!empty(self::$auth['user']))  {
            curl_setopt_array(self::$handle, array( CURLOPT_HTTPAUTH => self::$auth['method'], CURLOPT_USERPWD => self::$auth['user'] . ':' . self::$auth['pass'] ));
        }
        if (self::$proxy['address'] !== false)  {
            curl_setopt_array(self::$handle, array( CURLOPT_PROXYTYPE => self::$proxy['type'], CURLOPT_PROXY => self::$proxy['address'], CURLOPT_PROXYPORT => self::$proxy['port'], CURLOPT_HTTPPROXYTUNNEL => self::$proxy['tunnel'], CURLOPT_PROXYAUTH => self::$proxy['auth']['method'], CURLOPT_PROXYUSERPWD => self::$proxy['auth']['user'] . ':' . self::$proxy['auth']['pass'] ));
        }
        $response = curl_exec(self::$handle);
        $error = curl_error(self::$handle);
        $info = self::getInfo();
        if ($error)  {
            throw new Exception($error);
        }
        $header_size = $info['header_size'];
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        $httpCode = $info['http_code'];
        return new Response($httpCode, $body, $header, self::$jsonOpts);
    }
    public static function getInfo($opt = false)  {
        if ($opt)  {
            $info = curl_getinfo(self::$handle, $opt);
        }
        else  {
            $info = curl_getinfo(self::$handle);
        }
        return $info;
    }
    public static function getCurlHandle()  {
        return self::$handle;
    }
    public static function getFormattedHeaders($headers)  {
        $formattedHeaders = array();
        $combinedHeaders = array_change_key_case(array_merge(self::$defaultHeaders, (array) $headers));
        foreach ($combinedHeaders as $key => $val)  {
            $formattedHeaders[] = self::getHeaderString($key, $val);
        }
        if (!array_key_exists('user-agent', $combinedHeaders))  {
            $formattedHeaders[] = 'user-agent: woogram-php/2.0';
        }
        if (!array_key_exists('expect', $combinedHeaders))  {
            $formattedHeaders[] = 'expect:';
        }
        return $formattedHeaders;
    }
    private static function getArrayFromQuerystring($query)  {
        $query = preg_replace_callback('/(?:^|(?<=&))[^=[]+/', function ($match)  {
            return bin2hex(urldecode($match[0]));
        }
        , $query);
        parse_str($query, $values);
        return array_combine(array_map('hex2bin', array_keys($values)), $values);
    }
    private static function encodeUrl($url)  {
        $url_parsed = parse_url($url);
        $scheme = $url_parsed['scheme'] . '://';
        $host = $url_parsed['host'];
        $port = (isset($url_parsed['port']) ? $url_parsed['port'] : null);
        $path = (isset($url_parsed['path']) ? $url_parsed['path'] : null);
        $query = (isset($url_parsed['query']) ? $url_parsed['query'] : null);
        if ($query !== null)  {
            $query = '?' . http_build_query(self::getArrayFromQuerystring($query));
        }
        if ($port && $port[0] !== ':')  {
            $port = ':' . $port;
        }
        $result = $scheme . $host . $port . $path . $query;
        return $result;
    }
    private static function getHeaderString($key, $val)  {
        $key = trim(strtolower($key));
        return $key . ': ' . $val;
    }
    private static function mergeCurlOptions(&$existing_options, $new_options)  {
        $existing_options = $new_options + $existing_options;
        return $existing_options;
    }
}

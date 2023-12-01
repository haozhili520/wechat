<?php
/**
 * @copyright ©2018 Lu Wei
 * @author Lu Wei
 * @link http://www.Tmzkj.com/
 * Created by IntelliJ IDEA
 * Date Time: 2018/11/3 15:31
 */


namespace Tmzkj\Wechat;


use GuzzleHttp\Client;

/**
 * Class WechatHttpClient
 * @package Tmzkj\Wechat
 */
class WechatHttpClient
{
    const POST_DATA_TYPE_BODY = 'body';
    const POST_DATA_TYPE_FORM_PARAMS = 'form_params';
    const DATA_TYPE_JSON = 'json';
    const DATA_TYPE_XML = 'xml';

    public $dataType = 'json';
    public $postDataType = 'body';
    public $urlEncodeQueryString = true;

    private $sslCertPemFile;
    private $sslKeyPemFile;

    public function setDataType($dataType)
    {
        $this->dataType = $dataType;
        return $this;
    }

    public function setPostDataType($dataType)
    {
        $this->postDataType = $dataType;
        return $this;
    }

    public function setCertPemFile($file)
    {
        $this->sslCertPemFile = $file;
        return $this;
    }

    public function setKeyPemFile($file)
    {
        $this->sslKeyPemFile = $file;
        return $this;
    }

    /**
     * @param $url
     * @param array $params
     * @return mixed
     * @throws WechatException
     */
    public function get($url, $params = [])
    {
        return $this->curlSend([
            'url' => $this->appendParams($url, $params),
        ], 'get');
    }

    /**
     * @param $url
     * @param array|string $data
     * @param array $params
     * @return mixed
     * @throws WechatException
     */
    public function post($url, $data = [], $params = [])
    {
        return $this->curlSend([
            'url' => $this->appendParams($url, $params),
            'data' => $data,
        ], 'post');
    }

    /**
     * @param $args
     * @param $type
     * @return mixed
     * @throws WechatException
     */
    private function curlSend($args, $type)
    {
        $errorCodes = require __DIR__ . '/errors.php';
        try {
            if ($type === 'post') {
                $data = [];
                switch ($this->postDataType) {
                    case self::POST_DATA_TYPE_BODY:
                        $data['body'] = $args['data'];
                        break;
                    case self::POST_DATA_TYPE_FORM_PARAMS:
                        $data['form_params'] = $args['data'];
                        break;
                    default:
                        throw new WechatException('post暂不支持' . $this->postDataType . '请求方式');
                }
                $response = $this->getClient()->post($args['url'], $data);
            } else {
                $response = $this->getClient()->get($args['url'], [
                ]);
            }

            if ($response->getStatusCode() !== 200) {
                throw new WechatException($response->getStatusCode());
            }
            $body = $response->getBody();
            if ($this->dataType === static::DATA_TYPE_JSON) {
                $result = json_decode($body, true);
                if (!$result) {
                    throw new WechatException('微信接口返回的结果不是有效的json类型数据。Body: ' . $body);
                }
            } elseif ($this->dataType === static::DATA_TYPE_XML) {
                $result = WechatHelper::xmlToArray($body);
                if (!$result) {
                    throw new WechatException('微信接口返回的结果不是有效的xml类型数据。Body: ' . $body);
                }
            }
            if (isset($result['errcode']) && $result['errcode'] !== 0) {
                $errMsg = isset($errorCodes[$result['errcode']]) ?
                    $errorCodes[$result['errcode']]
                    : (isset($result['errmsg']) ? $result['errmsg'] : '');
                $message = 'errCode ' . $result['errcode'] . ($errMsg ? (', ' . $errMsg) : '');
                throw new WechatException($message, 0, null, $result);
            }
            return $result;
        } catch (WechatException $exception) {
            throw $exception;
        }
    }

    private function paramsToQueryString($params = [])
    {
        if (!is_array($params)) {
            return '';
        }
        if (!count($params)) {
            return '';
        }
        $str = '';
        foreach ($params as $k => $v) {
            if ($this->urlEncodeQueryString) {
                $v = urlencode($v);
            }
            $str .= "{$k}={$v}&";
        }
        return trim($str, '&');
    }

    private function appendParams($url, $params = [])
    {
        if (!is_array($params)) {
            return $url;
        }
        if (!count($params)) {
            return $url;
        }
        $url = trim($url, '?');
        $url = trim($url, '&');
        $queryString = $this->paramsToQueryString($params);
        if (mb_stripos($url, '?')) {
            return $url . '&' . $queryString;
        } else {
            return $url . '?' . $queryString;
        }
    }

    private function getClient()
    {
        $options = [
            'verify' => false,
        ];
        if ($this->sslCertPemFile) {
            $options['cert'] = $this->sslCertPemFile;
        }
        if ($this->sslKeyPemFile) {
            $options['ssl_key'] = $this->sslKeyPemFile;
        }
        return new Client($options);
    }
}

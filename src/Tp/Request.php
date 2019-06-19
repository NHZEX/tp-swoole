<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Tp;

class Request extends \think\Request
{
    /**
     * @return string
     */
    public function contentType(): string
    {
        $contentType = $this->header('CONTENT_TYPE');

        if ($contentType) {
            if (strpos($contentType, ';')) {
                list($type) = explode(';', $contentType);
            } else {
                $type = $contentType;
            }
            return trim($type);
        }

        return '';
    }

    /**
     * @return string
     */
    public function type(): string
    {
        $accept = $this->header('HTTP_ACCEPT');

        if (empty($accept)) {
            return '';
        }

        foreach ($this->mimeType as $key => $val) {
            $array = explode(',', $val);
            foreach ($array as $k => $v) {
                if (stristr($accept, $v)) {
                    return $key;
                }
            }
        }

        return '';
    }

    /**
     * @param $content
     * @return array
     */
    public function getInputData($content)
    {
        return parent::getInputData($content);
    }

    /**
     * @param array $put
     * @return Request
     */
    public function withPut(array $put)
    {
        $this->put = $put;
        return $this;
    }
}

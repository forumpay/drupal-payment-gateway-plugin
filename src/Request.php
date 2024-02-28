<?php

namespace Drupal\commerce_forumpay;

use Drupal\Component\Utility\Html;

/**
 * Encapsulates request parameter
 */
class Request
{
    /**
     * Return expected parameter for Request, throw \InvalidArgumentException otherwise.
     *
     * @param $paramName
     * @return mixed
     */
    public function getRequired($paramName)
    {
        $param = $this->get($paramName, null);
        if ($param === null) {
            throw new \InvalidArgumentException(sprintf('Missing required parameter %s', $paramName));
        }

        return $param;
    }

    /**
     * Return parameter for Request or default one if request one is not found
     *
     * @param $param
     * @param null $default
     * @return mixed
     */
    public function get($param, $default = null)
    {
        $params = $this->getAllParams();

        if (isset($params[$param])) {
            return Html::escape($params[$param]);
        }

        return $default;
    }

    private function getAllParams()
    {
        return array_merge(
            $_REQUEST,
            $this->getBodyParameters(),
        );
    }

    private function getBodyParameters()
    {
        $bodyContent = file_get_contents('php://input');
        return json_decode($bodyContent, true) ?? [];
    }
}

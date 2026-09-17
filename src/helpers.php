<?php

if (!interface_exists('Psr\Container\ContainerInterface')) {
    require __DIR__ . '/Psr11/ContainerExceptionInterface.php';
    require __DIR__ . '/Psr11/NotFoundExceptionInterface.php';
    require __DIR__ . '/Psr11/ContainerInterface.php';
}

if (!function_exists('container')) {
    /**
     * @param string $abstract
     * @param array $params
     *
     * @return mixed|\Wilkques\Container\Container
     */
    function container($abstract = null, $params = array())
    {
        if (is_null($abstract)) {
            return \Wilkques\Container\Container::getInstance();
        }

        return \Wilkques\Container\Container::getInstance()->make($abstract, $params);
    }
}
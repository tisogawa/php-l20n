<?php

namespace L20n\Platform;

/**
 * Class Platform\GlobalBase
 * @package L20n
 */
abstract class GlobalBase
{
    /** @var string|null */
    public $id = null;
    /** @var mixed|null */
    public $value = null;
    /** @var bool */
    public $isActive = false;

    /**
     * @return mixed
     */
    public function get()
    {
        if (!$this->value || !$this->isActive) {
            $this->value = $this->_get();
        }
        return $this->value;
    }

    /**
     * @return mixed
     */
    abstract protected function _get();
}

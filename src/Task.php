<?php

namespace izabolotnev;

/**
 * Class Task
 *
 * @author Ilya Zabolotnev <i.zabolotnev@rambler-co.ru>
 * @package izabolotnev
 */
abstract class Task
{

    /**
     * @return int Status code
     */
    public function run()
    {
        $this->afterFork();

        $status = $this->process();

        $this->beforeExit();

        return $status;
    }

    protected function afterFork()
    {
    }

    protected function beforeExit()
    {
    }

    /**
     * @return int Status code
     */
    abstract protected function process();

}

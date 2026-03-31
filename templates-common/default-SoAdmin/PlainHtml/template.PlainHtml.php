<?php

assert(isset($this) && $this instanceof Template);
echo $this->props['data']['content'];

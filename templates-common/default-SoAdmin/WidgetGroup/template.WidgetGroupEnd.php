<?php

assert(isset($this) && $this instanceof Template);

// Workaround to get rid of "Closing tag matches nothing" warning
echo Helpers::closingDiv();

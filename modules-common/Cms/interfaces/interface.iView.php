<?php

interface iView
{
	/**
	 * Processing the view, like echoing the content, or downloading a file.
	 *
	 * @return void
	 */
	public function view(): void;

	//public function download();
}

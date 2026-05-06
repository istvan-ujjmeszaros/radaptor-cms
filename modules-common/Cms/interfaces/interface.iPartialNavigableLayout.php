<?php

interface iPartialNavigableLayout
{
	/**
	 * @return list<string>
	 */
	public static function getPageFragmentTargets(): array;

	/**
	 * @return array<string, class-string<iLayoutComponent>>
	 */
	public static function getFragmentLayoutComponents(): array;
}

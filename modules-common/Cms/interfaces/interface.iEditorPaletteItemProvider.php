<?php

declare(strict_types=1);

interface iEditorPaletteItemProvider
{
	/**
	 * @return list<EditorPaletteItem>
	 */
	public function getPaletteItems(): array;

	/**
	 * @return list<EditorDropTarget>
	 */
	public function getDropTargets(): array;
}

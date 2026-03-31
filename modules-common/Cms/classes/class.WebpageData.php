<?php

class WebpageData
{
	public ?string $title;
	public ?string $layout_name;
	public ?string $keywords;
	public ?string $description;
	public ?string $robots_index;
	public ?string $robots_follow;
	public ?string $lang_id;

	public function __construct(
		public int $resource_id
	) {
		$attributes = ResourceTypeWebpage::getExtradata($resource_id);

		$this->title = $attributes['title'];
		$this->layout_name = $attributes['layout'];
		$this->keywords = $attributes['keywords'];
		$this->description = $attributes['description'];
		$this->robots_index = $attributes['robots_index'];
		$this->robots_follow = $attributes['robots_follow'];
		$this->lang_id = $attributes['lang_id'];
	}
}

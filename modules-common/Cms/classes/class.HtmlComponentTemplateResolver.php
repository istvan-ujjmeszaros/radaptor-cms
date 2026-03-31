<?php

declare(strict_types=1);

class HtmlComponentTemplateResolver
{
	/**
	 * @param array<string, mixed> $node
	 */
	public static function resolveTemplateName(array $node): string
	{
		$component = (string)($node['component'] ?? '');
		$props = is_array($node['props'] ?? null) ? $node['props'] : [];

		return match ($component) {
			'adminDropdown' => '_admin_dropdown',
			'statusMessage' => 'sdui.statusMessage',
			'form' => self::resolveFormTemplate($props),
			'form.row' => 'sdui.form.row',
			'form.helper' => 'sdui.form.helper',
			'form.input.text' => 'sdui.form.input.text',
			'form.input.password' => 'sdui.form.input.password',
			'form.input.hidden' => 'sdui.form.input.hidden',
			'form.input.textarea' => self::resolveTextareaTemplate($node),
			'form.input.select' => 'sdui.form.input.select',
			'form.input.checkbox' => 'sdui.form.input.checkbox',
			'form.input.checkboxgroup' => 'sdui.form.input.checkboxgroup',
			'form.input.radiogroup' => 'sdui.form.input.radiogroup',
			'form.input.date' => 'sdui.form.input.date',
			'form.input.datetime' => 'sdui.form.input.datetime',
			'form.input.clearfloat' => 'sdui.form.input.clearfloat',
			'form.input.groupend' => 'sdui.form.input.groupend',
			'form.input.widgetgroupbeginning' => 'sdui.form.input.widgetgroupbeginning',
			default => self::resolveDirectTemplate($component),
		};
	}

	/**
	 * @param array<string, mixed> $props
	 */
	private static function resolveFormTemplate(array $props): string
	{
		$form_name = trim((string)($props['form_name'] ?? ''));

		if ($form_name !== '') {
			$specific_template = 'sdui.form.' . $form_name;

			if (Template::checkTemplateIsRegistered($specific_template)) {
				return $specific_template;
			}
		}

		if (Template::checkTemplateIsRegistered('sdui.form')) {
			return 'sdui.form';
		}

		return '_missing';
	}

	private static function resolveDirectTemplate(string $component): string
	{
		if ($component !== '' && Template::checkTemplateIsRegistered($component)) {
			return $component;
		}

		return '_missing';
	}

	/**
	 * @param array<string, mixed> $node
	 */
	private static function resolveTextareaTemplate(array $node): string
	{
		$editor = (string)($node['meta']['html']['editor'] ?? '');

		if ($editor !== '') {
			$candidate = 'sdui.form.input.textarea.' . $editor;

			if (Template::checkTemplateIsRegistered($candidate)) {
				return $candidate;
			}
		}

		return 'sdui.form.input.textarea';
	}
}

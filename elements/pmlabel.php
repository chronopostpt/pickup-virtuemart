<?php
defined('JPATH_BASE') or die();

class JElementPMLabel extends JElement {

	var	$_name = 'pmlabel';

	function fetchElement($name, $value, &$node, $control_name) {
		$class = ( $node->attributes('class') ? 'class="'.$node->attributes('class').'"' : 'class="text_area"' );
		return '<label for="'.$name.'"'.$class.'>'.JText::_($value).'</label>';
	}

	public function render(&$xmlElement, $value, $control_name = 'params') {
		// Deprecation warning.
		JLog::add('JElement::render is deprecated.', JLog::WARNING, 'deprecated');

		$name = $xmlElement->attributes('name');
		$label = $xmlElement->attributes('label');
		$descr = $xmlElement->attributes('description');

		//make sure we have a valid label
		$label = $label ? $label : $name;
		$result[0] = NULL;
		// 		$result[0] = $this->fetchTooltip($label, $descr, $xmlElement, $control_name, $name);
		$result[1] = $this->fetchElement($name, $value, $xmlElement, $control_name);
		$result[2] = $descr;
		$result[3] = $label;
		$result[4] = $value;
		$result[5] = $name;

		return $result;
	}

}
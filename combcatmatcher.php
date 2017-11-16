<?php

class CombCatMatcher extends Module
{
	public function __construct()
	{
		$this->name = 'combcatmatcher';
		$this->tab = 'front_office_features';
		$this->verison = '0.1';
		$this->author = 'Brien Smith Martínez';
		$this->displayName = 'Combination-Category Matcher';
		$this->description = 'Module with overrides of CategoryController and Product Controller';

		parent::__construct();
	}

}
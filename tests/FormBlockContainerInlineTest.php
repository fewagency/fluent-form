<?php

use FewAgency\FluentForm\FormBlockContainer\FieldsetElement;
use FewAgency\FluentHtml\Testing\ComparesFluentHtml;
use FewAgency\FluentForm\FluentForm;

class FormBlockContainerInlineTest extends PHPUnit_Framework_TestCase
{
    use ComparesFluentHtml;

    public function testInlineForm()
    {
        $form = FluentForm::create()->inline();
        $form->withInputBlock('test');

        $this->assertHtmlEquals(
            '<form class="form-block-container form-block-container--inline" method="POST"> <span class="form-block"> <span><label class="form-block__label" for="test3">Test</label></span> <span> <input name="test" type="text" class="form-block__control" id="test3"> </span> </span> </form>',
            $form
        );
    }

    public function testAncestorInline()
    {
        $form = FluentForm::create()->inline();
        $form->withInputBlock('test');
        $fieldset = new FieldsetElement();
        $form->withContent($fieldset);
        $fieldset->withInputBlock('field');

        $this->assertContains('<span', $fieldset->toHtml());
        $this->assertNotContains('<div', $fieldset->toHtml());
    }
}
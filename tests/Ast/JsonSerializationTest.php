<?php

declare(strict_types=1);

describe('JSON Serialization', function (): void {
    describe('Base Node Properties', function (): void {
        it('includes all base properties in JSON output', function (): void {
            $template = 'Hello World';
            $doc = $this->parse($template);
            $nodes = $doc->getChildren();
            $json = $nodes[0]->jsonSerialize();

            expect($json)->toHaveKeys([
                'kind',
                'kind_name',
                'start',
                'end',
                'is_synthetic',
                'content',
                'children',
            ])
                ->and($json['start'])->toHaveKeys(['offset', 'line', 'column'])
                ->and($json['end'])->toHaveKeys(['offset', 'line', 'column'])
                ->and($json['children'])->toBeArray();
        });

        it('serializes children recursively', function (): void {
            $template = '<div><span>Text</span></div>';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];
            $json = $element->jsonSerialize();

            expect($json['children'])->toHaveCount(1);
            $spanJson = $json['children'][0];
            expect($spanJson['type'])->toBe('element')
                ->and($spanJson['tag_name_text'])->toBe('span')
                ->and($spanJson['children'])->toHaveCount(1);
            $textJson = $spanJson['children'][0];
            expect($textJson['type'])->toBe('text')
                ->and($textJson['text'])->toBe('Text');
        });
    });

    describe('TextNode', function (): void {
        it('serializes text nodes correctly', function (): void {
            $template = '  Hello World  ';
            $doc = $this->parse($template);
            $textNode = $doc->getChildren()[0];
            $json = $textNode->jsonSerialize();

            expect($json['type'])->toBe('text')
                ->and($json['text'])->toBe('  Hello World  ')
                ->and($json['is_whitespace'])->toBeFalse()
                ->and($json['has_significant_content'])->toBeTrue()
                ->and($json['trimmed_content'])->toBe('Hello World')
                ->and($json)->toHaveKeys(['leading_newlines', 'trailing_newlines']);
        });

        it('detects whitespace-only text', function (): void {
            $template = '<div>   </div>';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];
            $textChild = $element->getChildren()[0];
            $json = $textChild->jsonSerialize();

            expect($json['is_whitespace'])->toBeTrue()
                ->and($json['has_significant_content'])->toBeFalse();
        });
    });

    describe('EchoNode', function (): void {
        it('serializes escaped echo correctly', function (): void {
            $template = '{{ $variable }}';
            $doc = $this->parse($template);
            $echo = $doc->getChildren()[0];
            $json = $echo->jsonSerialize();

            expect($json['type'])->toBe('echo')
                ->and($json['echo_type'])->toBe('escaped')
                ->and($json['expression'])->toBe('$variable')
                ->and($json['inner_content'])->toBe(' $variable ')
                ->and($json['is_escaped'])->toBeTrue()
                ->and($json['is_raw'])->toBeFalse()
                ->and($json['is_triple'])->toBeFalse();
        });

        it('serializes raw echo correctly', function (): void {
            $template = '{!! $html !!}';
            $doc = $this->parse($template);
            $echo = $doc->getChildren()[0];
            $json = $echo->jsonSerialize();

            expect($json['echo_type'])->toBe('raw')
                ->and($json['is_raw'])->toBeTrue()
                ->and($json['is_escaped'])->toBeFalse();
        });

        it('serializes triple echo correctly', function (): void {
            $template = '{{{ $var }}}';
            $doc = $this->parse($template);
            $echo = $doc->getChildren()[0];
            $json = $echo->jsonSerialize();

            expect($json['echo_type'])->toBe('triple')
                ->and($json['is_triple'])->toBeTrue();
        });
    });

    describe('DirectiveNode', function (): void {
        it('serializes standalone directives', function (): void {
            $template = '@csrf';
            $doc = $this->parse($template);
            $directive = $doc->getChildren()[0];
            $json = $directive->jsonSerialize();

            expect($json['type'])->toBe('directive')
                ->and($json['name'])->toBe('csrf')
                ->and($json['original_name'])->toBe('csrf')
                ->and($json['arguments'])->toBeNull()
                ->and($json['has_arguments'])->toBeFalse()
                ->and($json['is_standalone'])->toBeTrue();
        });

        it('serializes directives with arguments', function (): void {
            $template = '@props([\'color\' => \'red\'])';
            $doc = $this->parse($template);
            $directive = $doc->getChildren()[0];
            $json = $directive->jsonSerialize();

            expect($json['name'])->toBe('props')
                ->and($json['has_arguments'])->toBeTrue()
                ->and($json['arguments'])->toBe("(['color' => 'red'])");
        });
    });

    describe('DirectiveBlockNode', function (): void {
        it('serializes directive blocks correctly', function (): void {
            $template = '@if($condition)Content@endif';
            $doc = $this->parse($template);
            $block = $doc->getChildren()[0];
            $json = $block->jsonSerialize();

            expect($json['type'])->toBe('directive_block')
                ->and($json['name'])->toBe('if')
                ->and($json['arguments'])->toBe('($condition)')
                ->and($json['is_if'])->toBeTrue()
                ->and($json['is_foreach'])->toBeFalse()
                ->and($json['start_directive_name'])->toBe('if')
                ->and($json)->toHaveKey('end_directive_name')
                ->and($json)->toHaveKey('children')
                ->and($json)->toHaveKey('intermediate_directives');
        });

        it('includes intermediate directives array', function (): void {
            $template = '@if($a)A@elseif($b)B@else C@endif';
            $doc = $this->parse($template);
            $block = $doc->getChildren()[0];
            $json = $block->jsonSerialize();

            expect($json)->toHaveKey('has_intermediates')
                ->and($json)->toHaveKey('intermediate_directives')
                ->and($json['intermediate_directives'])->toBeArray();
        });
    });

    describe('ElementNode', function (): void {
        it('serializes elements correctly', function (): void {
            $template = '<div class="container">Content</div>';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];
            $json = $element->jsonSerialize();

            expect($json['type'])->toBe('element')
                ->and($json['tag_name_text'])->toBe('div')
                ->and($json['is_void'])->toBeFalse()
                ->and($json['is_self_closing'])->toBeFalse()
                ->and($json['is_paired'])->toBeTrue()
                ->and($json['tag_name'])->toBeArray()
                ->and($json['tag_name']['type'])->toBe('element_name')
                ->and($json['tag_name']['name'])->toBe('div')
                ->and($json['attributes'])->toHaveCount(1);
        });

        it('serializes void elements correctly', function (): void {
            $template = '<br>';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];
            $json = $element->jsonSerialize();

            expect($json['is_void'])->toBeTrue()
                ->and($json['is_self_closing'])->toBeFalse();
        });

        it('serializes self-closing elements', function (): void {
            $template = '<img src="test.jpg" />';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];
            $json = $element->jsonSerialize();

            expect($json['is_self_closing'])->toBeTrue();
        });

        it('includes closing tag info for paired elements', function (): void {
            $template = '<div></div>';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];
            $json = $element->jsonSerialize();

            expect($json)->toHaveKey('closing_tag')
                ->and($json['closing_tag']['is_closing_name'])->toBeTrue();
        });
    });

    describe('Attributes', function (): void {
        it('serializes static attributes with nested name/value', function (): void {
            $template = '<div class="test"></div>';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];
            $json = $element->jsonSerialize();

            $attr = $json['attributes'][0];
            expect($attr['type'])->toBe('attribute')
                ->and($attr['attribute_type'])->toBe('static')
                ->and($attr['name_text'])->toBe('class')
                ->and($attr['value_text'])->toBe('test')
                ->and($attr['is_boolean'])->toBeFalse()
                ->and($attr['is_bound'])->toBeFalse()
                ->and($attr['name'])->toBeArray()
                ->and($attr['name']['type'])->toBe('attribute_name')
                ->and($attr['value'])->toBeArray()
                ->and($attr['value']['type'])->toBe('attribute_value');
        });

        it('serializes boolean attributes', function (): void {
            $template = '<input disabled>';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];
            $json = $element->jsonSerialize();

            $attr = $json['attributes'][0];
            expect($attr['is_boolean'])->toBeTrue()
                ->and($attr['name_text'])->toBe('disabled')
                ->and($attr['value_text'])->toBeNull();
        });

        it('serializes bound attributes', function (): void {
            $template = '<div :class="$class"></div>';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];
            $json = $element->jsonSerialize();

            $attr = $json['attributes'][0];
            expect($attr['is_bound'])->toBeTrue()
                ->and($attr['attribute_type'])->toBe('bound')
                ->and($attr['name_text'])->toBe('class');
        });

        it('serializes escaped attributes', function (): void {
            $template = '<div ::class="literal"></div>';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];
            $json = $element->jsonSerialize();

            $attr = $json['attributes'][0];
            expect($attr['is_escaped'])->toBeTrue()
                ->and($attr['attribute_type'])->toBe('escaped');
        });

        it('serializes complex attribute values with interpolation', function (): void {
            $template = '<div class="prefix-{{ $var }}-suffix"></div>';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];
            $json = $element->jsonSerialize();

            $attr = $json['attributes'][0];
            expect($attr['has_complex_value'])->toBeTrue()
                ->and($attr['value']['is_complex'])->toBeTrue()
                ->and($attr['value']['parts'])->toHaveCount(3);
        });

        it('serializes blade constructs as attributes', function (): void {
            $template = '<div {{ $attributes }}></div>';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];
            $json = $element->jsonSerialize();

            $attr = $json['attributes'][0];
            expect($attr['is_blade_construct'])->toBeTrue()
                ->and($attr)->toHaveKey('blade_construct')
                ->and($attr['blade_construct']['type'])->toBe('echo');
        });
    });

    describe('CommentNode', function (): void {
        it('serializes HTML comments', function (): void {
            $template = '<!-- This is a comment -->';
            $doc = $this->parse($template);
            $comment = $doc->getChildren()[0];
            $json = $comment->jsonSerialize();

            expect($json['type'])->toBe('html_comment')
                ->and($json['inner_content'])->toBe(' This is a comment ')
                ->and($json['is_empty'])->toBeFalse()
                ->and($json['has_close'])->toBeTrue();
        });
    });

    describe('BladeCommentNode', function (): void {
        it('serializes Blade comments', function (): void {
            $template = '{{-- Blade comment --}}';
            $doc = $this->parse($template);
            $comment = $doc->getChildren()[0];
            $json = $comment->jsonSerialize();

            expect($json['type'])->toBe('blade_comment')
                ->and($json['inner_content'])->toBe(' Blade comment ')
                ->and($json['text'])->toBe('Blade comment')
                ->and($json['is_empty'])->toBeFalse()
                ->and($json['has_close'])->toBeTrue();
        });
    });

    describe('PhpTagNode', function (): void {
        it('serializes PHP tags', function (): void {
            $template = '<?php echo "test"; ?>';
            $doc = $this->parse($template);
            $php = $doc->getChildren()[0];
            $json = $php->jsonSerialize();

            expect($json['type'])->toBe('php_tag')
                ->and($json['php_type'])->toBe('php')
                ->and($json['code'])->toBe('echo "test";')
                ->and($json['is_php_tag'])->toBeTrue()
                ->and($json['is_short_echo'])->toBeFalse()
                ->and($json['has_close'])->toBeTrue();
        });

        it('serializes short echo PHP tags', function (): void {
            $template = '<?= $var ?>';
            $doc = $this->parse($template);
            $php = $doc->getChildren()[0];
            $json = $php->jsonSerialize();

            expect($json['php_type'])->toBe('echo')
                ->and($json['is_short_echo'])->toBeTrue();
        });
    });

    describe('PhpBlockNode', function (): void {
        it('serializes PHP blocks', function (): void {
            $template = '@php $x = 1; @endphp';
            $doc = $this->parse($template);
            $block = $doc->getChildren()[0];
            $json = $block->jsonSerialize();

            expect($json['type'])->toBe('php_block')
                ->and($json['code'])->toBe('$x = 1;')
                ->and($json['has_close'])->toBeTrue();
        });
    });

    describe('VerbatimNode', function (): void {
        it('serializes verbatim blocks', function (): void {
            $template = '@verbatim{{ not parsed }}@endverbatim';
            $doc = $this->parse($template);
            $verbatim = $doc->getChildren()[0];
            $json = $verbatim->jsonSerialize();

            expect($json['type'])->toBe('verbatim')
                ->and($json['inner_content'])->toBe('{{ not parsed }}')
                ->and($json['has_close'])->toBeTrue();
        });
    });

    describe('EscapeNode', function (): void {
        it('serializes escape nodes', function (): void {
            $template = '@@if';
            $doc = $this->parse($template);
            $escape = $doc->getChildren()[0];
            $json = $escape->jsonSerialize();

            expect($json['type'])->toBe('escape')
                ->and($json['escaped_content'])->toBe('@');
        });
    });

    describe('DoctypeNode', function (): void {
        it('serializes DOCTYPE declarations', function (): void {
            $template = '<!DOCTYPE html>';
            $doc = $this->parse($template);
            $doctype = $doc->getChildren()[0];
            $json = $doctype->jsonSerialize();

            expect($json['type'])->toBe('doctype')
                ->and($json['doctype_type'])->toBe('html')
                ->and($json['is_html5'])->toBeTrue()
                ->and($json['is_xhtml'])->toBeFalse();
        });
    });

    describe('ComponentNode', function (): void {
        it('serializes component nodes', function (): void {
            $template = '<x-alert type="error">Message</x-alert>';
            $doc = $this->parse($template);
            $component = $doc->getChildren()[0];
            $json = $component->jsonSerialize();

            expect($json['type'])->toBe('component')
                ->and($json['component_type'])->toBe('blade')
                ->and($json['component_prefix'])->toBe('x-')
                ->and($json['component_name'])->toBe('alert')
                ->and($json['qualified_name'])->toBe('alert')
                ->and($json['is_slot_component'])->toBeFalse()
                ->and($json['has_slots'])->toBeFalse()
                ->and($json['has_default_slot'])->toBeTrue()
                ->and($json['slots_summary'])->toBeArray();
        });

        it('serializes components with slots', function (): void {
            $template = '<x-card><x-slot:header>Title</x-slot:header>Body</x-card>';
            $doc = $this->parse($template);
            $component = $doc->getChildren()[0];
            $json = $component->jsonSerialize();

            expect($json['has_slots'])->toBeTrue()
                ->and($json['slots_summary'])->toHaveCount(1)
                ->and($json['slots_summary'][0]['name'])->toBe('header');
        });
    });

    describe('SlotNode', function (): void {
        it('serializes slot nodes', function (): void {
            $template = '<x-card><x-slot:footer>Footer</x-slot:footer></x-card>';
            $doc = $this->parse($template);
            $component = $doc->getChildren()[0];
            $slot = iterator_to_array($component->slots())[0];
            $json = $slot->jsonSerialize();

            expect($json['type'])->toBe('slot')
                ->and($json['slot_is_dynamic'])->toBeFalse()
                ->and($json['slot_is_multi_value'])->toBeFalse()
                ->and($json['slot_base_name'])->toBe('footer');
        });
    });

    describe('Deep Nesting', function (): void {
        it('serializes deeply nested structures', function (): void {
            $template = '<div><section><article><p>{{ $text }}</p></article></section></div>';
            $doc = $this->parse($template);
            $json = $doc->getChildren()[0]->jsonSerialize();

            $section = $json['children'][0];
            $article = $section['children'][0];
            $p = $article['children'][0];

            expect($json['tag_name_text'])->toBe('div')
                ->and($section['tag_name_text'])->toBe('section')
                ->and($article['tag_name_text'])->toBe('article')
                ->and($p['tag_name_text'])->toBe('p')
                ->and($p['children'])->toHaveCount(1)
                ->and($p['children'][0]['type'])->toBe('echo');
        });

        it('serializes directive blocks with nested elements', function (): void {
            $template = '@foreach($items as $item)<div>{{ $item }}</div>@endforeach';
            $doc = $this->parse($template);
            $block = $doc->getChildren()[0];
            $json = $block->jsonSerialize();

            expect($json['type'])->toBe('directive_block')
                ->and($json['is_foreach'])->toBeTrue();

            $openingDirective = $json['children'][0];
            expect($openingDirective['type'])->toBe('directive');

            $elementChild = $openingDirective['children'][0];
            expect($elementChild['type'])->toBe('element')
                ->and($elementChild['tag_name_text'])->toBe('div');
        });
    });

    describe('JSON Encoding', function (): void {
        it('produces valid JSON', function (): void {
            $template = '<div class="test" :data="$var">{{ $content }}</div>';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];

            $encoded = json_encode($element->jsonSerialize(), JSON_THROW_ON_ERROR);
            $decoded = json_decode($encoded, true);

            expect($decoded)->toBeArray()
                ->and($decoded['type'])->toBe('element');
        });

        it('handles special characters in content', function (): void {
            $template = '<div>Special chars: " \' < > & {{ $var }}</div>';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];

            $encoded = json_encode($element->jsonSerialize(), JSON_THROW_ON_ERROR);
            $decoded = json_decode($encoded, true);

            expect($decoded)->toBeArray()
                ->and($decoded['children'][0]['text'])->toContain('Special chars:');
        });

        it('handles multibyte characters', function (): void {
            $template = '<div>Hello World</div>';
            $doc = $this->parse($template);
            $element = $doc->getChildren()[0];

            $encoded = json_encode($element->jsonSerialize(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $decoded = json_decode($encoded, true);

            expect($decoded['children'][0]['text'])->toContain('');
        });
    });
});

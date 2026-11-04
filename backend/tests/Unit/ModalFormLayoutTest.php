<?php

namespace Tests\Unit;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Guards against a real bug found in production use: a Bootstrap modal whose
 * <form> wraps modal-header/modal-body/modal-footer — rather than the form
 * itself carrying the "modal-content" class — breaks the modal's flex layout.
 * modal-header/body/footer must be `.modal-content`'s DIRECT children for
 * Bootstrap's CSS to size and scroll them correctly; wrapping them in a
 * plain `<div class="modal-content"><form>...</form></div>` makes the form
 * the sole flex child instead, so a tall form's footer (Save/Cancel) silently
 * renders past the modal's clipped height — present but unreachable, with no
 * visible scrollbar. It only showed up once "Admit Student" grew tall enough
 * to overflow a normal screen; every other modal had the same defect,
 * unnoticed only because their content happened to be short enough to fit.
 *
 * The fix is `<form class="modal-content" ...>` directly (merging the two),
 * not a wrapping div — this test fails on any (re)introduction of the wrapper form.
 */
class ModalFormLayoutTest extends TestCase
{
    public function test_no_modal_content_div_wraps_a_form_around_its_header_body_and_footer(): void
    {
        $offenders = [];

        foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            $contents = $file->getContents();

            if (preg_match('/<div class="modal-content">\s*<form\b/', $contents)) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, "These views wrap a <form> around modal-header/body/footer instead of\n"
            ."putting class=\"modal-content\" on the <form> itself — this breaks the modal's scrolling and can hide\n"
            ."its footer buttons off-screen on a tall form. See this test's class docblock.");
    }
}

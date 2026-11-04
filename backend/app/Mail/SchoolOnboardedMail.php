<?php

namespace App\Mail;

use App\Models\School;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to a new school's first School Administrator when Super Admin
 * onboards them — their login email and one-time temporary password. Until
 * this existed, that password only ever appeared once in the Super Admin's
 * own browser session (portal flash / API response) with no way to hand it
 * to the actual school if that got missed.
 */
class SchoolOnboardedMail extends Mailable
{
    public function __construct(public School $school, public User $admin, public string $temporaryPassword) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __(':school is ready on ElimuGrid', ['school' => $this->school->name]));
    }

    public function content(): Content
    {
        $loginUrl = config('app.url').'/login';

        return new Content(htmlString:
            '<p>'.e(__('Hello :name,', ['name' => $this->admin->name])).'</p>'
            .'<p>'.e(__(':school has been set up on ElimuGrid. You are its first School Administrator.', ['school' => $this->school->name])).'</p>'
            .'<p>'.e(__('Sign in at')).' <a href="'.e($loginUrl).'">'.e($loginUrl).'</a></p>'
            .'<p>'.e(__('Email')).': <strong>'.e($this->admin->email).'</strong><br>'
            .e(__('Temporary password')).': <strong>'.e($this->temporaryPassword).'</strong></p>'
            .'<p>'.e(__('Please change this password after signing in.')).'</p>'
        );
    }
}

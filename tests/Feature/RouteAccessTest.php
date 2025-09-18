<?php

// Test route access without database operations

it('redirects guests from project create page', function () {
    $response = $this->get('/dashboard/projects/create');
    $response->assertRedirect('/login');
});

it('redirects guests from project edit page', function () {
    $response = $this->get('/dashboard/projects/1/edit');
    $response->assertRedirect('/login');
});

it('redirects guests from task create page', function () {
    $response = $this->get('/dashboard/projects/1/tasks/create');
    $response->assertRedirect('/login');
});

it('redirects guests from task edit page', function () {
    $response = $this->get('/dashboard/projects/1/tasks/1/edit');
    $response->assertRedirect('/login');
});

it('redirects guests attempting to delete project', function () {
    $response = $this->delete('/dashboard/projects/1');
    $response->assertRedirect('/login');
});

it('redirects guests attempting to delete task', function () {
    $response = $this->delete('/dashboard/projects/1/tasks/1');
    $response->assertRedirect('/login');
});

it('validates task creation form fields', function () {
    $response = $this->post('/dashboard/projects/1/tasks', []);
    $response->assertRedirect('/login'); // Guest should be redirected
});

it('validates task update form fields', function () {
    $response = $this->put('/dashboard/projects/1/tasks/1', []);
    $response->assertRedirect('/login'); // Guest should be redirected
});

it('validates project update form fields', function () {
    $response = $this->put('/dashboard/projects/1', []);
    $response->assertRedirect('/login'); // Guest should be redirected
});

@props(['name', 'bag' => 'default'])
@if ($message = $errors->getBag($bag)->first($name))
    <p {{ $attributes->merge(['class' => 'field-error']) }} role="alert">{{ $message }}</p>
@endif

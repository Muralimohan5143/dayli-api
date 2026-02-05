<div x-data="{value: @entangle($attributes->wire('model')).live" , x-ref=" quillEditor" x-init="quill = new Quill($refs.quillEditor, {theme: 'snow'});
        quill.on('text-change', function () {
        $dispatch('input', quill.root.innerHTML);
        });" {{ $attributes->whereDoesntStartWith('wire:model.live') }} wire:ignore>
    <div id="editor"></div>
</div>

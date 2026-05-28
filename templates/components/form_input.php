<div class="form-group">
    <label class="form-label" for="<?= $inputId ?>"><?= $inputLabel ?></label>
    <input 
        type="<?= $inputType ?? 'text' ?>" 
        id="<?= $inputId ?>" 
        name="<?= $inputName ?>" 
        class="form-input" 
        placeholder="<?= $inputPlaceholder ?? '' ?>"
        <?= $inputRequired ?? false ? 'required' : '' ?>
        <?= isset($inputValue) ? 'value="' . htmlspecialchars($inputValue) . '"' : '' ?>
        <?= isset($inputMin) ? 'min="' . $inputMin . '"' : '' ?>
        <?= isset($inputMax) ? 'max="' . $inputMax . '"' : '' ?>
    >
</div>

<div class="users form content">
    <?= $this->Flash->render() ?>
    <h3>Change Password</h3>
    <?= $this->Form->create($user) ?>
    <fieldset>
        <legend><?= __('Please enter a new password') ?></legend>
        <?= $this->Form->control('password', [
            'type' => 'password',
            'required' => true,
            'label' => __('New Password'),
        ]) ?>
        <?= $this->Form->control('password_confirm', [
            'type' => 'password',
            'required' => true,
            'label' => __('Confirm Password'),
        ]) ?>
    </fieldset>
    <?= $this->Form->button(__('Save Password')) ?>
    <?= $this->Form->end() ?>

    <div class="actions">
        <?= $this->Html->link(__('Back to Profile'), ['controller' => 'Users', 'action' => 'profile'], ['class' => 'button']) ?>
    </div>
</div>
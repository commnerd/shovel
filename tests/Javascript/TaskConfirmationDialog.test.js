/**
 * @jest-environment jsdom
 */

import { mount } from '@vue/test-utils';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import TaskConfirmationDialog from '@/components/TaskConfirmationDialog.vue';

// Mock UI components
vi.mock('@/components/ui/button', () => ({
  Button: {
    name: 'Button',
    template: '<button><slot /></button>',
    props: ['variant', 'size', 'disabled'],
  }
}));

vi.mock('@/components/ui/card', () => ({
  Card: {
    name: 'Card',
    template: '<div class="card"><slot /></div>',
    props: ['class']
  },
  CardContent: {
    name: 'CardContent',
    template: '<div class="card-content"><slot /></div>',
  },
  CardHeader: {
    name: 'CardHeader',
    template: '<div class="card-header"><slot /></div>',
  },
  CardTitle: {
    name: 'CardTitle',
    template: '<div class="card-title"><slot /></div>',
  }
}));

vi.mock('@/components/ui/badge', () => ({
  Badge: {
    name: 'Badge',
    template: '<span class="badge"><slot /></span>',
    props: ['variant', 'class']
  }
}));

vi.mock('@/components/ui/scroll-area', () => ({
  ScrollArea: {
    name: 'ScrollArea',
    template: '<div class="scroll-area"><slot /></div>',
    props: ['class']
  }
}));

vi.mock('@/components/ui/dialog', () => ({
  Dialog: {
    name: 'Dialog',
    template: '<div v-if="open" class="dialog"><slot /></div>',
    props: ['open'],
    emits: ['update:open']
  },
  DialogContent: {
    name: 'DialogContent',
    template: '<div class="dialog-content"><slot /></div>',
    props: ['class']
  },
  DialogHeader: {
    name: 'DialogHeader',
    template: '<div class="dialog-header"><slot /></div>',
  },
  DialogTitle: {
    name: 'DialogTitle',
    template: '<div class="dialog-title"><slot /></div>',
  },
  DialogDescription: {
    name: 'DialogDescription',
    template: '<div class="dialog-description"><slot /></div>',
  },
  DialogFooter: {
    name: 'DialogFooter',
    template: '<div class="dialog-footer"><slot /></div>',
  }
}));

// Mock Lucide icons
vi.mock('lucide-vue-next', () => ({
  CheckCircle2: { name: 'CheckCircle2', template: '<svg class="check-circle" />' },
  Circle: { name: 'Circle', template: '<svg class="circle" />' },
  AlertCircle: { name: 'AlertCircle', template: '<svg class="alert-circle" />' },
  Sparkles: { name: 'Sparkles', template: '<svg class="sparkles" />' },
  Edit3: { name: 'Edit3', template: '<svg class="edit" />' },
  Trash2: { name: 'Trash2', template: '<svg class="trash" />' },
  Plus: { name: 'Plus', template: '<svg class="plus" />' },
}));

describe('TaskConfirmationDialog', () => {
  const mockTasks = [
    {
      title: 'Setup Development Environment',
      description: 'Configure development tools and dependencies',
      status: 'pending',
      priority: 'high',
      sort_order: 1,
    },
    {
      title: 'Design Database Schema',
      description: 'Create database tables and relationships',
      status: 'pending',
      priority: 'medium',
      sort_order: 2,
    },
    {
      title: 'Implement Authentication',
      description: 'Add user registration and login functionality',
      status: 'in_progress',
      priority: 'high',
      sort_order: 3,
    },
  ];

  const defaultProps = {
    open: true,
    tasks: mockTasks,
    projectDescription: 'Build a task management app',
    loading: false,
  };

  let wrapper;

  beforeEach(() => {
    wrapper = mount(TaskConfirmationDialog, {
      props: defaultProps,
      global: {
        stubs: {
          Dialog: true,
          DialogContent: true,
          DialogHeader: true,
          DialogTitle: true,
          DialogDescription: true,
          DialogFooter: true,
          Card: true,
          CardContent: true,
          CardHeader: true,
          CardTitle: true,
          Badge: true,
          ScrollArea: true,
          Button: true,
        }
      }
    });
  });

  it('renders the dialog when open is true', () => {
    expect(wrapper.find('[data-testid="task-confirmation-dialog"]').exists()).toBe(true);
  });

  it('displays the project description', () => {
    expect(wrapper.text()).toContain('Build a task management app');
  });

  it('renders all provided tasks', () => {
    const taskCards = wrapper.findAll('[data-testid^="suggested-task-"]');
    expect(taskCards).toHaveLength(3);
  });

  it('displays task details correctly', () => {
    const firstTask = wrapper.find('[data-testid="suggested-task-0"]');
    expect(firstTask.text()).toContain('Setup Development Environment');
    expect(firstTask.text()).toContain('Configure development tools and dependencies');
    expect(firstTask.text()).toContain('high');
    expect(firstTask.text()).toContain('pending');
  });

  it('allows editing task titles and descriptions', async () => {
    const editButton = wrapper.find('[data-testid="edit-task-0"]');
    await editButton.trigger('click');

    const titleInput = wrapper.find('input');
    expect(titleInput.exists()).toBe(true);
    expect(titleInput.element.value).toBe('Setup Development Environment');

    const descriptionTextarea = wrapper.find('textarea');
    expect(descriptionTextarea.exists()).toBe(true);
    expect(descriptionTextarea.element.value).toBe('Configure development tools and dependencies');
  });

  it('allows deleting tasks', async () => {
    const deleteButton = wrapper.find('[data-testid="delete-task-0"]');
    await deleteButton.trigger('click');

    // Task should be removed from the list
    const taskCards = wrapper.findAll('[data-testid^="suggested-task-"]');
    expect(taskCards).toHaveLength(2);
  });

  it('allows adding new tasks', async () => {
    const newTaskTitle = wrapper.find('[data-testid="new-task-title"]');
    const newTaskDescription = wrapper.find('[data-testid="new-task-description"]');
    const addButton = wrapper.find('[data-testid="add-task-button"]');

    await newTaskTitle.setValue('New Task');
    await newTaskDescription.setValue('New task description');
    await addButton.trigger('click');

    // Should have 4 tasks now
    const taskCards = wrapper.findAll('[data-testid^="suggested-task-"]');
    expect(taskCards).toHaveLength(4);
  });

  it('toggles task status when status icon is clicked', async () => {
    const statusButton = wrapper.find('[data-testid="task-status-0"]');
    await statusButton.trigger('click');

    // Status should change from pending to in_progress
    expect(wrapper.vm.editableTasks[0].status).toBe('in_progress');
  });

  it('changes task priority when priority badge is clicked', async () => {
    const priorityBadge = wrapper.find('[data-testid="task-priority-0"]');
    await priorityBadge.trigger('click');

    // Priority should change from high to low (cycling through)
    expect(wrapper.vm.editableTasks[0].priority).toBe('low');
  });

  it('emits confirm event with tasks when confirmed', async () => {
    const confirmButton = wrapper.find('[data-testid="confirm-tasks"]');
    await confirmButton.trigger('click');

    expect(wrapper.emitted().confirm).toBeTruthy();
    expect(wrapper.emitted().confirm[0][0]).toEqual(wrapper.vm.editableTasks);
  });

  it('emits cancel event when cancelled', async () => {
    const cancelButton = wrapper.find('[data-testid="cancel-tasks"]');
    await cancelButton.trigger('click');

    expect(wrapper.emitted().cancel).toBeTruthy();
    expect(wrapper.emitted()['update:open']).toBeTruthy();
    expect(wrapper.emitted()['update:open'][0][0]).toBe(false);
  });

  it('emits regenerate event when regenerate is clicked', async () => {
    const regenerateButton = wrapper.find('[data-testid="regenerate-tasks"]');
    await regenerateButton.trigger('click');

    expect(wrapper.emitted().regenerate).toBeTruthy();
  });

  it('shows loading state', async () => {
    await wrapper.setProps({ loading: true });

    expect(wrapper.text()).toContain('Generating tasks with AI...');
    expect(wrapper.find('[data-testid="regenerate-tasks"]').attributes('disabled')).toBeDefined();
    expect(wrapper.find('[data-testid="confirm-tasks"]').attributes('disabled')).toBeDefined();
  });

  it('disables confirm button when no tasks', async () => {
    await wrapper.setProps({ tasks: [] });

    const confirmButton = wrapper.find('[data-testid="confirm-tasks"]');
    expect(confirmButton.attributes('disabled')).toBeDefined();
  });

  it('updates button text based on task count', async () => {
    const confirmButton = wrapper.find('[data-testid="confirm-tasks"]');
    expect(confirmButton.text()).toContain('Create Project with 3 Tasks');

    // Delete a task
    const deleteButton = wrapper.find('[data-testid="delete-task-0"]');
    await deleteButton.trigger('click');

    expect(confirmButton.text()).toContain('Create Project with 2 Tasks');
  });

  it('handles empty task title gracefully', async () => {
    const newTaskTitle = wrapper.find('[data-testid="new-task-title"]');
    const addButton = wrapper.find('[data-testid="add-task-button"]');

    await newTaskTitle.setValue('');
    expect(addButton.attributes('disabled')).toBeDefined();
  });

  it('preserves task order when editing', async () => {
    const initialOrder = wrapper.vm.editableTasks.map(task => task.title);

    // Edit first task
    const editButton = wrapper.find('[data-testid="edit-task-0"]');
    await editButton.trigger('click');

    const titleInput = wrapper.find('input');
    await titleInput.setValue('Updated Task Title');

    const saveButton = wrapper.find('button');
    await saveButton.trigger('click');

    // Order should be preserved
    const currentOrder = wrapper.vm.editableTasks.map(task => task.title);
    expect(currentOrder[0]).toBe('Updated Task Title');
    expect(currentOrder[1]).toBe(initialOrder[1]);
    expect(currentOrder[2]).toBe(initialOrder[2]);
  });

  it('initializes editable tasks when dialog opens', async () => {
    await wrapper.setProps({ open: false });
    await wrapper.setProps({ open: true });

    expect(wrapper.vm.editableTasks).toEqual(mockTasks);
  });
});

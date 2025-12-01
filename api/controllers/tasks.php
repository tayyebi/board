<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../models/Task.php';
require_once __DIR__ . '/../models/Lane.php';

/**
 * Tasks Controller - handles tasks REST endpoints
 */
class TasksController {
    private TaskModel $taskModel;
    private LaneModel $laneModel;
    
    public function __construct(string $dataDir) {
        $this->taskModel = new TaskModel($dataDir);
        $this->laneModel = new LaneModel($dataDir);
    }
    
    public function handle(string $method, array $pathParts): void {
        $id = $pathParts[0] ?? '';
        $action = $pathParts[1] ?? '';
        
        switch ($method) {
            case 'GET':
                $this->handleGet($id);
                break;
            case 'POST':
                $this->handlePost($action);
                break;
            case 'PUT':
                $this->handlePut($id, $action);
                break;
            case 'DELETE':
                $this->handleDelete($id);
                break;
            default:
                json_error('Method not allowed', 405);
        }
    }
    
    private function handleGet(string $id): void {
        if ($id === '' || $id === 'grouped') {
            $this->getGrouped();
        } elseif ($id === 'list') {
            $this->getList();
        } elseif ($id === 'stats') {
            $this->getStats();
        } else {
            $this->getById($id);
        }
    }
    
    private function handlePost(string $action): void {
        $data = get_json_input();
        $this->createTask($data);
    }
    
    private function handlePut(string $id, string $action): void {
        $data = get_json_input();
        
        if ($action === 'move') {
            $this->moveTask($id, $data);
        } else {
            $this->updateTask($id, $data);
        }
    }
    
    private function handleDelete(string $id): void {
        $this->deleteTask($id);
    }
    
    private function getList(): void {
        json_response([
            'tasks' => $this->taskModel->getDecoded(),
            'lastModified' => $this->taskModel->getLastModified()
        ]);
    }
    
    private function getGrouped(): void {
        $swimlanes = $this->laneModel->getStructured();
        $tasks = $this->taskModel->getDecoded();
        
        // Initialize grouped structure
        $group = [];
        foreach ($swimlanes as $swl => $meta) {
            $group[$swl] = [];
            foreach ($meta['cols'] as $col) {
                $group[$swl][$col] = [];
            }
        }
        
        // Group tasks
        foreach ($tasks as $task) {
            $swl = $task['swimlane'];
            $col = $task['column'];
            
            if (!$this->laneModel->validSwimlane($swl)) {
                $swl = $this->laneModel->firstSwimlane();
            }
            if (!$this->laneModel->validColumn($swl, $col)) {
                $col = $this->laneModel->firstColumn($swl);
            }
            
            if (isset($group[$swl][$col])) {
                $group[$swl][$col][] = $task;
            }
        }
        
        json_response([
            'grouped' => $group,
            'swimlanes' => $swimlanes,
            'lastModified' => [
                'tasks' => $this->taskModel->getLastModified(),
                'lanes' => $this->laneModel->getLastModified()
            ]
        ]);
    }
    
    private function getStats(): void {
        json_response([
            'count' => $this->taskModel->getCount(),
            'tasksLastModified' => $this->taskModel->getLastModified(),
            'lanesLastModified' => $this->laneModel->getLastModified()
        ]);
    }
    
    private function getById(string $id): void {
        $task = $this->taskModel->getById($id);
        if ($task === null) {
            json_error('Task not found', 404);
        }
        json_response(['task' => $task]);
    }
    
    private function createTask(array $data): void {
        $title = trim($data['title'] ?? '');
        $notes = $data['notes'] ?? '';
        $swimlane = trim($data['swimlane'] ?? '');
        $column = trim($data['column'] ?? '');
        $due = trim($data['due'] ?? '');
        
        if ($title === '') {
            json_error('Title is required');
        }
        
        if (!$this->laneModel->validSwimlane($swimlane)) {
            json_error('Invalid swimlane', 400);
        }
        
        if (!$this->laneModel->validColumn($swimlane, $column)) {
            json_error('Invalid column', 400);
        }
        
        $id = $this->taskModel->create($title, $notes, $swimlane, $column, $due);
        json_response([
            'message' => 'Task created',
            'id' => $id,
            'task' => $this->taskModel->getById($id)
        ], 201);
    }
    
    private function updateTask(string $id, array $data): void {
        $existing = $this->taskModel->getById($id);
        if ($existing === null) {
            json_error('Task not found', 404);
        }
        
        $title = trim($data['title'] ?? '');
        $notes = $data['notes'] ?? '';
        $swimlane = trim($data['swimlane'] ?? '');
        $column = trim($data['column'] ?? '');
        $due = trim($data['due'] ?? '');
        
        if ($swimlane !== '' && !$this->laneModel->validSwimlane($swimlane)) {
            json_error('Invalid swimlane', 400);
        }
        
        if ($swimlane !== '' && $column !== '' && !$this->laneModel->validColumn($swimlane, $column)) {
            json_error('Invalid column', 400);
        }
        
        // Use existing values if not provided
        if ($swimlane === '') $swimlane = $existing['swimlane'];
        if ($column === '') $column = $existing['column'];
        
        if ($this->taskModel->update($id, $title, $notes, $swimlane, $column, $due)) {
            json_response([
                'message' => 'Task updated',
                'task' => $this->taskModel->getById($id)
            ]);
        } else {
            json_error('Failed to update task', 500);
        }
    }
    
    private function moveTask(string $id, array $data): void {
        $swimlane = trim($data['swimlane'] ?? '');
        $column = trim($data['column'] ?? '');
        
        if (!$this->laneModel->validSwimlane($swimlane)) {
            json_error('Invalid swimlane', 400);
        }
        
        if (!$this->laneModel->validColumn($swimlane, $column)) {
            json_error('Invalid column', 400);
        }
        
        if ($this->taskModel->move($id, $swimlane, $column)) {
            json_response(['message' => 'Task moved']);
        } else {
            json_error('Task not found', 404);
        }
    }
    
    private function deleteTask(string $id): void {
        if ($this->taskModel->delete($id)) {
            json_response(['message' => 'Task deleted']);
        } else {
            json_error('Failed to delete task', 500);
        }
    }
}

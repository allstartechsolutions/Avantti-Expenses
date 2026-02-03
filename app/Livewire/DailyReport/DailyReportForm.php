<?php

namespace App\Livewire\DailyReport;

use App\Models\DailyReport;
use App\Models\DailyReportImage;
use App\Models\DailyReportManpower;
use App\Models\DailyReportTask;
use App\Models\DailyReportWeather;
use App\Models\DailyReportWeatherObservation;
use App\Models\JobSite;
use App\Models\Project;
use App\Services\WeatherService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class DailyReportForm extends Component
{
    use WithFileUploads;

    public ?Project $project = null;
    public ?JobSite $jobSite = null;
    public ?DailyReport $dailyReport = null;
    public $mode = 'create'; // 'create' or 'edit'
    public $context = 'jobsite'; // 'jobsite' or 'project'

    // Daily Report properties
    public $report_date = '';

    // Tasks array - each task will have: id, description, images, order
    public $tasks = [];

    // Task modal state
    public $showTaskModal = false;
    public $taskModalMode = 'create';
    public $editingTaskIndex = null;
    public $taskDescription = '';
    public $taskImages = [];
    public $existingTaskImages = [];

    // Weather data from API
    public $weather = null;
    public $weatherLoading = false;
    public $weatherError = null;

    // Weather observations (manual entries)
    public $weatherObservations = [];

    // Weather observation modal state
    public $showObservationModal = false;
    public $observationModalMode = 'create';
    public $editingObservationIndex = null;

    // Weather observation form fields
    public $observation_time = '';
    public $observation_weather_delay = false;
    public $observation_sky = '';
    public $observation_temp = '';
    public $observation_wind = '';
    public $observation_precip = '';
    public $observation_notes = '';

    // Manpower logs array
    public $manpowerLogs = [];

    // Manpower modal state
    public $showManpowerModal = false;
    public $manpowerModalMode = 'create';
    public $editingManpowerIndex = null;

    // Manpower form fields
    public $manpower_contact_company = '';
    public $manpower_workers = 1;
    public $manpower_works = '';
    public $manpower_hours = '';
    public $manpower_comments = '';
    public $manpowerImages = [];
    public $existingManpowerImages = [];

    protected function rules()
    {
        return [
            'report_date' => 'required|date',
            'taskDescription' => 'required|string|min:10',
        ];
    }

    public function mount(?JobSite $jobSite = null, ?Project $project = null, ?DailyReport $dailyReport = null)
    {
        // Determine the context based on what was passed
        if ($jobSite && $jobSite->exists) {
            $this->jobSite = $jobSite->load('project');
            $this->project = $this->jobSite->project;
            $this->context = 'jobsite';
        } elseif ($project && $project->exists) {
            $this->project = $project;
            $this->jobSite = null;
            $this->context = 'project';
        }

        if ($dailyReport && $dailyReport->exists) {
            $this->dailyReport = $dailyReport->load(['tasks.images', 'preparedBy', 'weather', 'weatherObservations', 'manpowerLogs.images', 'jobSite', 'project']);
            $this->mode = 'edit';
            $this->report_date = $dailyReport->report_date->format('Y-m-d');

            // Set context based on the daily report
            if ($dailyReport->job_site_id) {
                $this->jobSite = $dailyReport->jobSite;
                $this->project = $dailyReport->project;
                $this->context = 'jobsite';
            } else {
                $this->project = $dailyReport->project;
                $this->jobSite = null;
                $this->context = 'project';
            }

            // Load existing tasks
            $this->tasks = $dailyReport->tasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'description' => $task->description,
                    'images' => $task->images->toArray(),
                    'order' => $task->order,
                ];
            })->toArray();

            // Load existing weather data
            if ($dailyReport->weather) {
                $this->weather = $dailyReport->weather->toArray();
            }

            // Load existing weather observations
            $this->weatherObservations = $dailyReport->weatherObservations->map(function ($obs) {
                return [
                    'id' => $obs->id,
                    'observed_at' => $obs->observed_at ? $obs->observed_at->format('H:i') : '',
                    'weather_delay' => $obs->weather_delay,
                    'sky_condition' => $obs->sky_condition,
                    'temperature' => $obs->temperature,
                    'wind_condition' => $obs->wind_condition,
                    'precipitation' => $obs->precipitation,
                    'notes' => $obs->notes,
                    'order' => $obs->order,
                ];
            })->toArray();

            // Load existing manpower logs
            $this->manpowerLogs = $dailyReport->manpowerLogs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'contact_company' => $log->contact_company,
                    'workers' => $log->workers,
                    'works' => $log->works,
                    'hours' => $log->hours,
                    'comments' => $log->comments,
                    'images' => $log->images->toArray(),
                    'order' => $log->order,
                ];
            })->toArray();
        } else {
            $this->mode = 'create';
            $this->report_date = now()->format('Y-m-d');
        }
    }

    public function openAddTaskModal()
    {
        $this->reset(['taskDescription', 'taskImages', 'existingTaskImages', 'editingTaskIndex']);
        $this->taskModalMode = 'create';
        $this->showTaskModal = true;
        $this->dispatch('open-modal', 'task-modal');
    }

    public function openEditTaskModal($index)
    {
        $task = $this->tasks[$index];
        $this->editingTaskIndex = $index;
        $this->taskDescription = $task['description'];
        $this->existingTaskImages = $task['images'] ?? [];
        $this->taskImages = [];
        $this->taskModalMode = 'edit';
        $this->showTaskModal = true;
        $this->dispatch('open-modal', 'task-modal');
    }

    public function saveTask()
    {
        $this->validate([
            'taskDescription' => 'required|string|min:10',
            'taskImages.*' => 'nullable|image|max:10240',
        ]);

        // Process new uploaded images
        $newImages = [];
        if ($this->taskImages) {
            foreach ($this->taskImages as $image) {
                $newImages[] = [
                    'temp_path' => $image->store('temp_daily_reports', 'local'),
                    'file_name' => $image->getClientOriginalName(),
                    'file_size' => $image->getSize(),
                ];
            }
        }

        // Combine existing images with new uploads
        $allImages = array_merge($this->existingTaskImages, $newImages);

        $task = [
            'description' => $this->taskDescription,
            'images' => $allImages,
            'order' => $this->taskModalMode === 'create' ? count($this->tasks) : $this->tasks[$this->editingTaskIndex]['order'],
        ];

        if ($this->taskModalMode === 'edit') {
            $task['id'] = $this->tasks[$this->editingTaskIndex]['id'] ?? null;
            $this->tasks[$this->editingTaskIndex] = $task;
        } else {
            $this->tasks[] = $task;
        }

        $this->closeTaskModal();
        session()->flash('task_message', 'Task ' . ($this->taskModalMode === 'edit' ? 'updated' : 'added') . ' successfully!');
    }

    public function removeTaskImage($taskIndex, $imageIndex)
    {
        if (isset($this->tasks[$taskIndex]['images'][$imageIndex])) {
            array_splice($this->tasks[$taskIndex]['images'], $imageIndex, 1);
            session()->flash('task_message', 'Image removed successfully!');
        }
    }

    public function removeModalImage($imageIndex)
    {
        if (isset($this->existingTaskImages[$imageIndex])) {
            array_splice($this->existingTaskImages, $imageIndex, 1);
        }
    }

    public function removeNewImage($imageIndex)
    {
        if (isset($this->taskImages[$imageIndex])) {
            array_splice($this->taskImages, $imageIndex, 1);
        }
    }

    public function removeTask($index)
    {
        array_splice($this->tasks, $index, 1);
        session()->flash('task_message', 'Task removed successfully!');
    }

    public function closeTaskModal()
    {
        $this->showTaskModal = false;
        $this->reset(['taskDescription', 'taskImages', 'existingTaskImages', 'editingTaskIndex']);
        $this->dispatch('close-modal', 'task-modal');
    }

    // Weather methods
    public function fetchWeather()
    {
        // Get coordinates from job site or project
        $latitude = $this->jobSite?->latitude ?? $this->project?->latitude;
        $longitude = $this->jobSite?->longitude ?? $this->project?->longitude;

        // Check if we have coordinates
        if (!$latitude || !$longitude) {
            $location = $this->jobSite ? 'Job site' : 'Project';
            $this->weatherError = "{$location} does not have coordinates. Please add an address with geocoding first.";
            return;
        }

        // Check if API is configured
        $weatherService = new WeatherService();
        if (!$weatherService->isConfigured()) {
            $this->weatherError = 'Weather API is not configured. Please add VISUAL_CROSSING_API_KEY to your .env file.';
            return;
        }

        $this->weatherLoading = true;
        $this->weatherError = null;

        $weatherData = $weatherService->fetchWeatherForDate(
            $latitude,
            $longitude,
            $this->report_date
        );

        $this->weatherLoading = false;

        if ($weatherData) {
            $this->weather = $weatherData;
            $this->weather['fetched_at'] = now()->toDateTimeString();
            session()->flash('weather_message', 'Weather data fetched successfully!');
        } else {
            $this->weatherError = 'Failed to fetch weather data. Please try again.';
        }
    }

    public function clearWeather()
    {
        $this->weather = null;
        $this->weatherError = null;
    }

    // Weather observation methods
    public function openAddObservationModal()
    {
        $this->reset([
            'observation_time',
            'observation_weather_delay',
            'observation_sky',
            'observation_temp',
            'observation_wind',
            'observation_precip',
            'observation_notes',
            'editingObservationIndex',
        ]);
        $this->observation_time = now()->format('H:i');
        $this->observationModalMode = 'create';
        $this->showObservationModal = true;
        $this->dispatch('open-modal', 'observation-modal');
    }

    public function openEditObservationModal($index)
    {
        $obs = $this->weatherObservations[$index];
        $this->editingObservationIndex = $index;
        $this->observation_time = $obs['observed_at'] ?? '';
        $this->observation_weather_delay = $obs['weather_delay'] ?? false;
        $this->observation_sky = $obs['sky_condition'] ?? '';
        $this->observation_temp = $obs['temperature'] ?? '';
        $this->observation_wind = $obs['wind_condition'] ?? '';
        $this->observation_precip = $obs['precipitation'] ?? '';
        $this->observation_notes = $obs['notes'] ?? '';
        $this->observationModalMode = 'edit';
        $this->showObservationModal = true;
        $this->dispatch('open-modal', 'observation-modal');
    }

    public function saveObservation()
    {
        $this->validate([
            'observation_time' => 'required',
            'observation_sky' => 'required|string',
            'observation_wind' => 'required|string',
            'observation_precip' => 'required|string',
            'observation_temp' => 'nullable|numeric',
        ]);

        $observation = [
            'observed_at' => $this->observation_time,
            'weather_delay' => $this->observation_weather_delay,
            'sky_condition' => $this->observation_sky,
            'temperature' => $this->observation_temp ?: null,
            'wind_condition' => $this->observation_wind,
            'precipitation' => $this->observation_precip,
            'notes' => $this->observation_notes,
            'order' => $this->observationModalMode === 'create'
                ? count($this->weatherObservations)
                : $this->weatherObservations[$this->editingObservationIndex]['order'] ?? count($this->weatherObservations),
        ];

        if ($this->observationModalMode === 'edit') {
            $observation['id'] = $this->weatherObservations[$this->editingObservationIndex]['id'] ?? null;
            $this->weatherObservations[$this->editingObservationIndex] = $observation;
        } else {
            $this->weatherObservations[] = $observation;
        }

        $this->closeObservationModal();
        session()->flash('observation_message', 'Weather observation ' . ($this->observationModalMode === 'edit' ? 'updated' : 'added') . ' successfully!');
    }

    public function removeObservation($index)
    {
        array_splice($this->weatherObservations, $index, 1);
        session()->flash('observation_message', 'Weather observation removed successfully!');
    }

    public function closeObservationModal()
    {
        $this->showObservationModal = false;
        $this->reset([
            'observation_time',
            'observation_weather_delay',
            'observation_sky',
            'observation_temp',
            'observation_wind',
            'observation_precip',
            'observation_notes',
            'editingObservationIndex',
        ]);
        $this->dispatch('close-modal', 'observation-modal');
    }

    // Manpower log methods
    public function openAddManpowerModal()
    {
        $this->reset([
            'manpower_contact_company',
            'manpower_workers',
            'manpower_works',
            'manpower_hours',
            'manpower_comments',
            'manpowerImages',
            'existingManpowerImages',
            'editingManpowerIndex',
        ]);
        $this->manpower_workers = 1;
        $this->manpowerModalMode = 'create';
        $this->showManpowerModal = true;
        $this->dispatch('open-modal', 'manpower-modal');
    }

    public function openEditManpowerModal($index)
    {
        $log = $this->manpowerLogs[$index];
        $this->editingManpowerIndex = $index;
        $this->manpower_contact_company = $log['contact_company'];
        $this->manpower_workers = $log['workers'] ?? 1;
        $this->manpower_works = $log['works'];
        $this->manpower_hours = $log['hours'];
        $this->manpower_comments = $log['comments'] ?? '';
        $this->existingManpowerImages = $log['images'] ?? [];
        $this->manpowerImages = [];
        $this->manpowerModalMode = 'edit';
        $this->showManpowerModal = true;
        $this->dispatch('open-modal', 'manpower-modal');
    }

    public function saveManpower()
    {
        $this->validate([
            'manpower_contact_company' => 'required|string|max:255',
            'manpower_workers' => 'required|integer|min:1',
            'manpower_works' => 'required|string',
            'manpower_hours' => 'required|numeric|min:0|max:24',
            'manpower_comments' => 'nullable|string',
            'manpowerImages.*' => 'nullable|image|max:10240',
        ]);

        // Process new uploaded images
        $newImages = [];
        if ($this->manpowerImages) {
            foreach ($this->manpowerImages as $image) {
                $newImages[] = [
                    'temp_path' => $image->store('temp_daily_reports', 'local'),
                    'file_name' => $image->getClientOriginalName(),
                    'file_size' => $image->getSize(),
                ];
            }
        }

        // Combine existing images with new uploads
        $allImages = array_merge($this->existingManpowerImages, $newImages);

        $manpowerLog = [
            'contact_company' => $this->manpower_contact_company,
            'workers' => $this->manpower_workers,
            'works' => $this->manpower_works,
            'hours' => $this->manpower_hours,
            'comments' => $this->manpower_comments ?: null,
            'images' => $allImages,
            'order' => $this->manpowerModalMode === 'create' ? count($this->manpowerLogs) : $this->manpowerLogs[$this->editingManpowerIndex]['order'],
        ];

        if ($this->manpowerModalMode === 'edit') {
            $manpowerLog['id'] = $this->manpowerLogs[$this->editingManpowerIndex]['id'] ?? null;
            $this->manpowerLogs[$this->editingManpowerIndex] = $manpowerLog;
        } else {
            $this->manpowerLogs[] = $manpowerLog;
        }

        $this->closeManpowerModal();
        session()->flash('manpower_message', 'Manpower log ' . ($this->manpowerModalMode === 'edit' ? 'updated' : 'added') . ' successfully!');
    }

    public function removeManpowerImage($logIndex, $imageIndex)
    {
        if (isset($this->manpowerLogs[$logIndex]['images'][$imageIndex])) {
            array_splice($this->manpowerLogs[$logIndex]['images'], $imageIndex, 1);
            session()->flash('manpower_message', 'Image removed successfully!');
        }
    }

    public function removeManpowerModalImage($imageIndex)
    {
        if (isset($this->existingManpowerImages[$imageIndex])) {
            array_splice($this->existingManpowerImages, $imageIndex, 1);
        }
    }

    public function removeNewManpowerImage($imageIndex)
    {
        if (isset($this->manpowerImages[$imageIndex])) {
            array_splice($this->manpowerImages, $imageIndex, 1);
        }
    }

    public function removeManpower($index)
    {
        array_splice($this->manpowerLogs, $index, 1);
        session()->flash('manpower_message', 'Manpower log removed successfully!');
    }

    public function closeManpowerModal()
    {
        $this->showManpowerModal = false;
        $this->reset([
            'manpower_contact_company',
            'manpower_workers',
            'manpower_works',
            'manpower_hours',
            'manpower_comments',
            'manpowerImages',
            'existingManpowerImages',
            'editingManpowerIndex',
        ]);
        $this->dispatch('close-modal', 'manpower-modal');
    }

    // Helper methods for the view
    public function getTemperatureUnit()
    {
        return WeatherService::getTemperatureUnit();
    }

    public function formatTemperature($fahrenheit)
    {
        return WeatherService::formatTemperature($fahrenheit);
    }

    public function formatPrecipitation($inches)
    {
        return WeatherService::formatPrecipitation($inches);
    }

    public function formatWindSpeed($mph)
    {
        return WeatherService::formatWindSpeed($mph);
    }

    public function getSkyConditions()
    {
        return DailyReportWeatherObservation::SKY_CONDITIONS;
    }

    public function getWindConditions()
    {
        return DailyReportWeatherObservation::WIND_CONDITIONS;
    }

    public function getPrecipitationTypes()
    {
        return DailyReportWeatherObservation::PRECIPITATION_TYPES;
    }

    public function save()
    {
        $this->validate([
            'report_date' => 'required|date',
        ]);

        if (empty($this->tasks)) {
            session()->flash('error', 'Please add at least one task to the daily report.');
            return;
        }

        if ($this->mode === 'edit' && $this->dailyReport) {
            if (!$this->dailyReport->isEditable() && !Auth::user()->is_admin) {
                session()->flash('error', 'This report is no longer editable.');
                return $this->redirectBack();
            }

            $this->dailyReport->update([
                'report_date' => $this->report_date,
            ]);

            // Delete existing tasks
            $this->dailyReport->tasks()->delete();

            $dailyReport = $this->dailyReport;
        } else {
            $dailyReport = DailyReport::create([
                'project_id' => $this->project->id,
                'job_site_id' => $this->jobSite?->id,
                'report_date' => $this->report_date,
                'prepared_by' => Auth::id(),
            ]);
        }

        // Create tasks
        foreach ($this->tasks as $index => $taskData) {
            $task = $dailyReport->tasks()->create([
                'description' => $taskData['description'],
                'order' => $index,
            ]);

            // Handle images for this task
            if (!empty($taskData['images'])) {
                foreach ($taskData['images'] as $imageData) {
                    if (is_array($imageData)) {
                        // Check if this is a temp file or existing file
                        if (isset($imageData['temp_path'])) {
                            // Move from temp to permanent storage
                            $permanentPath = 'daily_reports/' . $dailyReport->id . '/' . basename($imageData['temp_path']);
                            Storage::move($imageData['temp_path'], $permanentPath);

                            $task->images()->create([
                                'file_path' => $permanentPath,
                                'file_name' => $imageData['file_name'],
                                'file_size' => $imageData['file_size'],
                                'uploaded_by' => Auth::id(),
                            ]);
                        } elseif (isset($imageData['file_path'])) {
                            // Existing image - just recreate the record
                            $task->images()->create([
                                'file_path' => $imageData['file_path'],
                                'file_name' => $imageData['file_name'],
                                'file_size' => $imageData['file_size'],
                                'uploaded_by' => $imageData['uploaded_by'] ?? Auth::id(),
                            ]);
                        }
                    }
                }
            }
        }

        // Save weather data
        if ($this->weather) {
            // Delete existing weather record if updating
            $dailyReport->weather()->delete();

            $dailyReport->weather()->create([
                'temp_low' => $this->weather['temp_low'] ?? null,
                'temp_high' => $this->weather['temp_high'] ?? null,
                'temp_avg' => $this->weather['temp_avg'] ?? null,
                'precip_midnight' => $this->weather['precip_midnight'] ?? null,
                'precip_2_days' => $this->weather['precip_2_days'] ?? null,
                'precip_3_days' => $this->weather['precip_3_days'] ?? null,
                'humidity_low' => $this->weather['humidity_low'] ?? null,
                'humidity_avg' => $this->weather['humidity_avg'] ?? null,
                'humidity_high' => $this->weather['humidity_high'] ?? null,
                'dew_point' => $this->weather['dew_point'] ?? null,
                'wind_avg' => $this->weather['wind_avg'] ?? null,
                'wind_max' => $this->weather['wind_max'] ?? null,
                'wind_gust' => $this->weather['wind_gust'] ?? null,
                'snapshots' => $this->weather['snapshots'] ?? null,
                'conditions' => $this->weather['conditions'] ?? null,
                'icon' => $this->weather['icon'] ?? null,
                'fetched_at' => $this->weather['fetched_at'] ?? now(),
            ]);
        }

        // Save weather observations
        // Delete existing observations if updating
        $dailyReport->weatherObservations()->delete();

        foreach ($this->weatherObservations as $index => $obsData) {
            $dailyReport->weatherObservations()->create([
                'observed_at' => $obsData['observed_at'],
                'weather_delay' => $obsData['weather_delay'] ?? false,
                'sky_condition' => $obsData['sky_condition'],
                'temperature' => $obsData['temperature'],
                'wind_condition' => $obsData['wind_condition'],
                'precipitation' => $obsData['precipitation'],
                'notes' => $obsData['notes'] ?? null,
                'order' => $index,
            ]);
        }

        // Save manpower logs
        // Delete existing manpower logs if updating
        $dailyReport->manpowerLogs()->delete();

        foreach ($this->manpowerLogs as $index => $logData) {
            $manpowerLog = $dailyReport->manpowerLogs()->create([
                'contact_company' => $logData['contact_company'],
                'workers' => $logData['workers'] ?? 1,
                'works' => $logData['works'],
                'hours' => $logData['hours'],
                'comments' => $logData['comments'] ?? null,
                'order' => $index,
            ]);

            // Handle images for this manpower log
            if (!empty($logData['images'])) {
                foreach ($logData['images'] as $imageData) {
                    if (is_array($imageData)) {
                        if (isset($imageData['temp_path'])) {
                            // Move from temp to permanent storage
                            $permanentPath = 'daily_reports/' . $dailyReport->id . '/manpower/' . basename($imageData['temp_path']);
                            Storage::move($imageData['temp_path'], $permanentPath);

                            $manpowerLog->images()->create([
                                'file_path' => $permanentPath,
                                'file_name' => $imageData['file_name'],
                                'file_size' => $imageData['file_size'],
                                'uploaded_by' => Auth::id(),
                            ]);
                        } elseif (isset($imageData['file_path'])) {
                            // Existing image - just recreate the record
                            $manpowerLog->images()->create([
                                'file_path' => $imageData['file_path'],
                                'file_name' => $imageData['file_name'],
                                'file_size' => $imageData['file_size'],
                                'uploaded_by' => $imageData['uploaded_by'] ?? Auth::id(),
                            ]);
                        }
                    }
                }
            }
        }

        session()->flash('message', 'Daily report ' . ($this->mode === 'edit' ? 'updated' : 'created') . ' successfully!');
        return $this->redirectBack();
    }

    public function cancel()
    {
        return $this->redirectBack();
    }

    protected function redirectBack()
    {
        if ($this->context === 'project' || !$this->jobSite) {
            return redirect()->route('projects.daily-reports', $this->project);
        }
        return redirect()->route('jobsites.overview', $this->jobSite);
    }

    public function render()
    {
        return view('livewire.daily-report.daily-report-form')
            ->layout('components.layouts.app');
    }
}

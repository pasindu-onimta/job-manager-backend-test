<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::group(['middleware' => 'auth:api', 'prefix' => 'authorization', 'namespace' => 'Auth'], function () {
    Route::post('role/add', 'AuthorizationController@storeRoles');
    Route::post('permission/add', 'AuthorizationController@storePermissions');
    Route::post('permission/give/{role_id}', 'AuthorizationController@givePermissionToARole');
    Route::post('role/assign/{user_id}', 'AuthorizationController@assignARoleToAUser');
    Route::get('roles/{user_id}', 'AuthorizationController@roleNames');
});

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth',
    'namespace' => 'Auth',
], function ($router) {
    Route::post('login', 'AuthController@login');
    Route::post('logout', 'AuthController@logout');
    Route::post('refresh', 'AuthController@refresh');
    Route::get('me', 'AuthController@me');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'admin',
    'namespace' => 'Admin',
], function ($router) {
    Route::get('allActivities', 'ActivityLogController@index');
    Route::get('allFeatures', 'FeaturesController@allFeatures');
    Route::post('changeFeatureStatus', 'FeaturesController@changeFeatureStatus');
});

Route::group([
    'middleware' => 'auth:api',
    'namespace' => 'Kanban',
], function ($router) {
    Route::post('sections', 'SectionController@index');
    Route::get('jobs', 'JobsController@index');
    Route::get('jobs/details/admin', 'JobsController@loadAllJobsForAdmin');
    Route::get('jobs/details/{id}', 'JobsController@loadJobDetails');
    Route::post('jobs/all', 'JobsController@getAllJobs');
    Route::post('jobs', 'JobsController@store');
    Route::put('jobs', 'JobsController@updateJob');
    Route::get('all_jobs', 'JobsController@loadAllJobs');
    Route::get('all_jobs/user/{id}', 'JobsController@loadAllJobsForUser');
    Route::get('jobs/qc', 'JobsController@indexQC');
    Route::get('jobs/sync', 'JobsController@syncJobs');
    Route::get('job/tasks/{id}', 'TasksController@loadJobTasks');
    Route::get('job/tasks/{id}/{user}', 'TasksController@loadJobTasksForUser');
    Route::post('jobs/assign/quick_job', 'JobsController@storeQuickJob');
    Route::post('jobs/change/due_date', 'JobsController@changeJobDueDate');
    Route::get('jobs/approvals/pending', 'JobsController@pendingApprovals');
    Route::get('tasks/allTasks', 'TasksController@loadAllTasks');
    Route::get('tasks/all-tasks/assigned', 'TasksController@loadAllAssignedTasks');
    Route::get('tasks/sub_tasks/{id}', 'TasksController@loadSubTasks');
    Route::post('tasks/sub_tasks', 'TasksController@saveSubTask');
    Route::post('tasks/plan_date', 'TasksController@savePlanDate');
    Route::post('tasks/due_date/change', 'TasksController@changeTaskDueDate');
    Route::post('tasks/estimate_time/change', 'TasksController@changeEstimateTime');
    Route::post('tasks/assign', 'TasksController@store');
    Route::post('tasks', 'TasksController@index');
    Route::post('tasks/single', 'TasksController@single');
    Route::post('tasks/singleToday', 'TasksController@singleToday');
    Route::post('tasks/approve', 'TasksController@approveTasks');
    Route::post('tasks/approve/request', 'TasksController@sendApproveRequest');
    Route::post('tasks/updateQcEmployee', 'TasksController@updateQcEmployee');
    Route::post('activity', 'ActivityController@store');
    Route::post('activity/change', 'ActivityController@change');
    Route::post('activity/change/qc', 'ActivityController@changeQcTask');
    Route::post('activity/change/quickjob', 'ActivityController@changeQuickJobsToOngoing');
});

Route::group(['middleware' => 'auth:api'], function () {
    // users
    Route::get('users', 'UserController@index');
    Route::get('users/{division}', 'UserController@usersByDivision');
    Route::get('users/division/{division}', 'UserController@getUsersByDivision');
    Route::post('ongoing_jobs', 'DashboardController@ongoingJobs');
    Route::post('summary', 'DashboardController@summary');
    Route::post('summary/tasks', 'DashboardController@tasks_summary');
    Route::post('summary/activities/tasks', 'DashboardController@daily_tasks_summary');
    Route::post('summary/activities', 'DashboardController@activity_summary');
    Route::post('tasks/change/dashboard', 'DashboardController@dashboardTaskChange');
    Route::post('tasks/change/dashboard/restore', 'DashboardController@restoreLastOngoingTask');
    Route::get('notifications', 'NotificationController@index');
    Route::get('mobileapp/ongoing_jobs', 'DashboardController@mobileAppOngoing');

    Route::post('settings/generate', 'SettingsController@generateId');
});

Route::group([
    'middleware' => 'auth:api',
    'namespace' => 'Settings',
], function ($router) {
    Route::get('employee/getdepartment', 'EmployeeController@getdep');
    Route::get('employee/getposition/{id}', 'EmployeeController@getPosition');
    Route::post('employee/addemployee', 'EmployeeController@addemployee');
    Route::get('employee/listemployee', 'EmployeeController@getEmployees');
    Route::get('employee/getemployee/{id}', 'EmployeeController@getEmployee');
    Route::get('customers/sync', 'CustomersController@customerSync');
    Route::get('customers', 'CustomersController@index');
    Route::get('customers/{customer}', 'CustomersController@loadSelectedCustomer');
    Route::get('customers/agreement/{id}', 'CustomersController@loadSelectedBranchAgreements');
    Route::post('customers', 'CustomersController@store');
    Route::get('divisions', 'DivisionController@index');
});

Route::group([
    'middleware' => 'auth:api'
], function ($router) {
    Route::post('service', 'ServiceController@store');
    Route::post('service/load', 'ServiceController@index');
});

// Reports
Route::group([
    'middleware' => 'auth:api',
    'namespace' => 'Reports',
    'prefix' => 'reports'
], function ($router) {
    Route::post('emp_daily_activity', 'EmployeeDailyActivityReport@index');
    Route::post('all_emp_daily_activity', 'EmployeeDailyActivityReport@all_emp_daily_activity');
    Route::post('uploads/worksheet', 'EmployeeDailyActivityReport@uploadToWorksheet');
    Route::post('uploads/isEmployeeDayEnd', 'EmployeeDailyActivityReport@isEmployeeDayEnd');
    Route::post('job_allocations', 'JobAllocationReport@index');
    Route::post('job_allocations/tasks', 'JobAllocationReport@tasks');
});

Route::post('activity/comment/add', 'Kanban\ActivityController@storeComments');
Route::get('user/{user}/tasks', 'Kanban\TasksController@loadTasksForUser');
Route::put('user/task_plan', 'Kanban\TasksController@updateTaskDates');
Route::get('user/{user}/jobs', 'Kanban\JobsController@getJobsWithTasks');
Route::get('job/{job_id}', 'Kanban\JobsController@getJobById');

Route::get('user/{user_id}/plan', 'ActivityPlanController@getUserPlan');
Route::post('activity-plan', 'ActivityPlanController@store');
Route::delete('activity-plan/{id}', 'ActivityPlanController@delete');
Route::post('jobs/summary', 'DashboardController@jobs_summary');

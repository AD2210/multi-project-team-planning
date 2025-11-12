import { startStimulusApp } from '@symfony/stimulus-bundle';
import PlannerGrid from '@mptp/planner_grid_controller';
import PlannerToolbar from '@mptp/planner_toolbar_controller';
import PlannerDetail from '@mptp/planner_detail_controller';
import PlannerGridMonth from '@mptp/planner_grid_month_controller';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);

app.register('planner-grid', PlannerGrid);
app.register('planner-toolbar', PlannerToolbar);
app.register('planner-detail', PlannerDetail);
app.register('planner-grid-month', PlannerGridMonth);

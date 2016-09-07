@if($actions->count() > 0)
@foreach($actions as $action)
<div class="panel panel-default">
    <div class="panel-heading">
        <strong>
            {{ $action->name }}
            @if($action->description)
            <i class="ion ion-ios-help-outline" data-toggle="tooltip" data-title="{{ $action->description }}"></i>
            @endif
        </strong>
    </div>
    <div class="panel-body">
        <canvas id="action-{{ $action->id }}" data-start-at="{{ $action->start_at }}" data-completion-latency="{{ $action->completion_latency }}" data-action-id="{{ $action->id }}" height="160" width="600"></canvas>
    </div>
</div>
@endforeach
<script>
(function () {
    Chart.defaults.global.elements.point.hitRadius = 10;
    Chart.defaults.global.responsiveAnimationDuration = 1000;
    Chart.defaults.global.legend.display = false;

    var charts = {};

    $('canvas[data-action-id]').each(function() {
        drawChart($(this));
    });

    function drawChart($el) {
        var actionId = $el.data('action-id');

        if (typeof charts[actionId] === 'undefined') {
            charts[actionId] = {
                context: document.getElementById("action-"+actionId).getContext("2d"),
                chart: null,
            };
        }

        var chart = charts[actionId];

        $.getJSON('/actions/'+actionId).done(function (result) {
            var data = result.data.items;

            if (chart.chart !== null) {
                chart.chart.destroy();
            }

            var chartData = _.values(data);
            var chartKeys = _.keys(data);

            var yLabels = _.map(chartData, function (data) {
                return data.completed_at;
            });

            chart.chart = new Chart(chart.context, {
                type: 'line',
                data: {
                    labels: _.keys(chartData),
                    datasets: [{
                        label: "Associated time period start time",
                        lineTension: 0,
                        data: _.map(chartData, function (data, index) {
                            var startAt = moment(chartKeys[index]);
                            if (data.completed_at) {
                                var completedAt = moment(startAt.format('YYYY-MM-DD')+' '+data.completed_at.split(' ')[1]);

                                return completedAt.diff(startAt, 'seconds');
                            }

                            return 0; // TODO: Make this the max value.
                        }),
                        fill: false,
                        backgroundColor: "{{ $theme_metrics }}",
                        borderColor: "{{ color_darken($theme_metrics, -0.1) }}",
                        pointBackgroundColor: _.map(chartData, function (data, index) {
                            var startAt = moment(chartKeys[index]);
                            if (data.completed_at) {
                                var completedAt = moment(startAt.format('YYYY-MM-DD')+' '+data.completed_at.split(' ')[1]);
                                var targettedAt = moment(startAt.format('YYYY-MM-DD')).add($el.data('completion-latency'), 's');

                                if (completedAt.isAfter(targettedAt, 'hour')) {
                                    return "{{ color_darken($theme_yellows, -0.1) }}";
                                }

                                return "{{ color_darken($theme_metrics, -0.1) }}";
                            }

                            return "{{ color_darken($theme_reds, -0.1) }}";
                        }),
                        pointBorderColor: "{{ color_darken($theme_metrics, -0.1) }}",
                        pointHoverBackgroundColor: "{{ color_darken($theme_metrics, -0.2) }}",
                        pointHoverBorderColor: "{{ color_darken($theme_metrics, -0.2) }}",
                    }, {
                        lineTension: 0,
                        data: Array.from({ length: chartData.length }, function () { return $el.data('completion-latency'); }),
                        fill: false,
                    }]
                },
                options: {
                    scales: {
                        yAxes: [{
                            ticks: {
                                min: 0,
                                callback: function (value, index, values) {
                                    var time = moment();
                                    time.hours(0);
                                    time.seconds(0);

                                    return time.add(value, 'seconds').format('HH:mm');
                                }
                            }
                        }],
                        xAxes: [{
                            ticks: {
                                callback: function (value, index, values) {
                                    return _.keys(data)[index]
                                }
                            },
                            scaleLabel: {
                                display: true,
                                labelString: 'Associated time period start time',
                            }
                        }]
                    },
                    tooltips: {
                        callbacks: {
                            beforeLabel: function (tooltipItem, data) {
                                if (yLabels[tooltipItem.index]) {
                                    return 'Completed at: '+ yLabels[tooltipItem.index];
                                } else {
                                    return 'Did not complete.';
                                }
                            },
                            label: function(tooltipItem, data) {
                                // We can safely assume use of index 0
                                return 'Target completion time: ' + moment(tooltipItem.xLabel).add(data.datasets[1].data[0], 's').format('YYYY-MM-DD HH:mm');
                            }
                        }
                    }
                }
            });
        });
    }
}());
</script>
@endif

@extends('layouts/contentLayoutMaster')

@section('title', 'Scorecard Details')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="text-center mb-4">
                    <h4>HI-TECH GROUP</h4>
                    <h5>ANNUAL PERFORMANCE REVIEW | {{ $scorecard->cycle->name ?? 'N/A' }}</h5>
                    <p class="text-muted">INDIVIDUAL APPRAISAL REPORT</p>
                </div>
                
                <table class="table table-bordered mb-4">
                    <tr>
                        <th>Employee Code</th>
                        <td>{{ $scorecard->user->code ?? 'N/A' }}</td>
                        <th>Full Name</th>
                        <td>{{ $scorecard->user->first_name ?? '' }} {{ $scorecard->user->last_name ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>Total Score</th>
                        <td><strong>{{ $scorecard->total_score }} / 100</strong></td>
                        <th>Grade</th>
                        <td><strong>{{ $scorecard->grade }}</strong></td>
                    </tr>
                </table>

                <h5 class="mt-4">PERFORMANCE SCORECARD</h5>
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Parameter</th>
                            <th>Value Achieved</th>
                            <th>Points Earned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>Revenue Performance (Bingo)</td>
                            <td>{{ number_format($scorecard->revenue_achieved) }} / {{ number_format($scorecard->revenue_target) }}</td>
                            <td>{{ $scorecard->revenue_score }}</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>Pipeline Strength</td>
                            <td>{{ number_format($scorecard->pipeline_value) }}</td>
                            <td>{{ $scorecard->pipeline_score }}</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td>YoY Revenue Growth</td>
                            <td>{{ $scorecard->yoy_growth_percentage }}%</td>
                            <td>{{ $scorecard->yoy_growth_score }}</td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td>Trial Activity / Demos</td>
                            <td>{{ $scorecard->demos_conducted }} conducted</td>
                            <td>{{ $scorecard->demos_score }}</td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td>New Customer Wins (%)</td>
                            <td>{{ $scorecard->new_customer_revenue_percentage }}%</td>
                            <td>{{ $scorecard->new_customer_score }}</td>
                        </tr>
                        <tr>
                            <td>6</td>
                            <td>90-Day Goals</td>
                            <td>{{ $scorecard->ninety_day_goal_achievement }}% success on 12-week grid</td>
                            <td>{{ $scorecard->ninety_day_goal_score }}</td>
                        </tr>
                        <tr>
                            <td>7</td>
                            <td>Sales Pitch Score</td>
                            <td>{{ $scorecard->sales_pitch_score_raw }} / 20</td>
                            <td>{{ $scorecard->sales_pitch_score }}</td>
                        </tr>
                        <tr>
                            <td>8</td>
                            <td>Learn@HiTech</td>
                            <td>{{ $scorecard->learn_hitech_programs }} programs</td>
                            <td>{{ $scorecard->learn_hitech_score }}</td>
                        </tr>
                        <tr>
                            <td>9</td>
                            <td>Seminars / Presentations</td>
                            <td>{{ $scorecard->seminars_conducted }} seminars</td>
                            <td>{{ $scorecard->seminars_score }}</td>
                        </tr>
                        <tr>
                            <td>10</td>
                            <td>Competitor Insights</td>
                            <td>{{ $scorecard->competitor_insights_count }} insights</td>
                            <td>{{ $scorecard->competitor_insights_score }}</td>
                        </tr>
                        <tr>
                            <td>11</td>
                            <td>New Product Ideas</td>
                            <td>{{ $scorecard->product_ideas_count }} ideas</td>
                            <td>{{ $scorecard->product_ideas_score }}</td>
                        </tr>
                        <tr>
                            <td>12</td>
                            <td>Cost of Selling (COS%)</td>
                            <td>{{ $scorecard->cost_of_selling_percentage }}%</td>
                            <td>{{ $scorecard->cost_of_selling_score }}</td>
                        </tr>
                        <tr>
                            <td>13</td>
                            <td>Cross-Sell / Basket Mix</td>
                            <td>{{ $scorecard->cross_sell_dominant_percentage }}% dominant product</td>
                            <td>{{ $scorecard->cross_sell_score }}</td>
                        </tr>
                        <tr>
                            <td>14</td>
                            <td>Market Share %</td>
                            <td>{{ $scorecard->market_share_percentage }}%</td>
                            <td>{{ $scorecard->market_share_score }}</td>
                        </tr>
                        <tr class="table-danger">
                            <td>-</td>
                            <td>Attrition Penalty</td>
                            <td>{{ $scorecard->attrition_rate }}%</td>
                            <td>{{ $scorecard->attrition_penalty }}</td>
                        </tr>
                        <tr class="table-primary fw-bold">
                            <td colspan="3" class="text-end">TOTAL SCORE:</td>
                            <td>{{ $scorecard->total_score }} / 100</td>
                        </tr>
                    </tbody>
                </table>
                
                @if(Auth::user()->hasRole(['admin', 'hr', 'manager']))
                <div class="mt-4">
                    <h5>Manager Review Input (Only visible to managers)</h5>
                    <form action="" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Manager Comments</label>
                                <textarea name="manager_comments" class="form-control" rows="3">{{ $scorecard->manager_comments }}</textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sales Pitch Score (out of 20)</label>
                                <input type="number" name="sales_pitch_score_raw" class="form-control" value="{{ $scorecard->sales_pitch_score_raw }}" max="20" min="0">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">Save Review</button>
                    </form>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

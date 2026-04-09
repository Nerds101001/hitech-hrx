<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip - {{ $payslip->code }}</title>
    <style>
        @page { margin: 20px; }
        body { font-family: sans-serif; font-size: 8pt; color: #000; margin: 0; padding: 0; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .mb-1 { margin-bottom: 4px; }
        .mb-3 { margin-bottom: 15px; }
        .border-top { border-top: 1px solid #000; }
        .pt-1 { padding-top: 4px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
        table, td { border: 1px solid #000; }
        td { padding: 4px 6px; vertical-align: top; overflow: hidden; word-wrap: break-word; }
        
        .bg-grey { background-color: #f2f2f2; }
        .header-section { margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .net-payable { background-color: #000; color: #fff; font-weight: bold; padding: 8px; font-size: 10pt; }
        
        .clearfix:after { content: ""; display: table; clear: both; }
        .footer { margin-top: 40px; }
        .signature { border-top: 1px solid #000; width: 150px; text-align: center; float: right; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="header-section text-center">
        <h2 style="margin: 0; font-size: 14pt;">HI TECH GROUP</h2>
        <div style="font-size: 7pt;">{{ $company['address'] }}</div>
        <div style="font-size: 7pt;">{{ $company['email'] }} | {{ $company['phone'] }}</div>
    </div>

    <div style="margin-bottom: 10px; border-bottom: 1px solid #000; padding-bottom: 5px;">
        <span style="float: left;" class="fw-bold">(Under Payment of Wages Act)</span>
        <span style="float: right;" class="fw-bold">SALARY SLIP {{ $payslip->created_at->format('F, Y') }}</span>
        <div style="clear: both;"></div>
    </div>

    <table>
        <tbody>
            <tr>
                <td width="15%" class="bg-grey fw-bold">Department:</td>
                <td width="35%">{{ $compliance['dept'] }}</td>
                <td width="15%" class="bg-grey fw-bold">Emp. Name:</td>
                <td width="35%">{{ $user->full_name }}</td>
            </tr>
            <tr>
                <td class="bg-grey fw-bold">Emp Personal ID:</td>
                <td>{{ $user->id }}</td>
                <td class="bg-grey fw-bold">Father's Name:</td>
                <td>{{ $compliance['father_name'] }}</td>
            </tr>
            <tr>
                <td class="bg-grey fw-bold">Emp Computer ID:</td>
                <td>{{ $compliance['computer_id'] }}</td>
                <td class="bg-grey fw-bold">Mobile No:</td>
                <td>{{ $user->phone }}</td>
            </tr>
            <tr>
                <td class="bg-grey fw-bold">Joining Date:</td>
                <td>{{ $compliance['joining_date'] }}</td>
                <td class="bg-grey fw-bold">Bank Ac No:</td>
                <td>{{ $compliance['bank_ac'] }}</td>
            </tr>
            <tr>
                <td class="bg-grey fw-bold">ESI No:</td>
                <td>{{ $compliance['esi_no'] }}</td>
                <td class="bg-grey fw-bold">Aadhar No:</td>
                <td>{{ $compliance['aadhar'] }}</td>
            </tr>
            <tr>
                <td class="bg-grey fw-bold">PF No:</td>
                <td>{{ $compliance['pf_no'] }}</td>
                <td class="bg-grey fw-bold">PAN:</td>
                <td>{{ $compliance['pan'] }}</td>
            </tr>
            <tr>
                <td class="bg-grey fw-bold">UAN No:</td>
                <td>{{ $compliance['uan_no'] }}</td>
                <td class="bg-grey fw-bold">Designation:</td>
                <td>{{ $user->designation->name ?? 'N/A' }}</td>
            </tr>
        </tbody>
    </table>

    <table>
        <tr class="bg-grey fw-bold text-center">
            <td>Days Work</td>
            <td>Off Days</td>
            <td>Sick Leave</td>
            <td>Earned Leave</td>
            <td>Casual Leave</td>
            <td>National Holiday</td>
            <td>Total Days</td>
            <td>OT Min.</td>
        </tr>
        <tr class="text-center">
            <td>{{ $attendance['worked'] }}</td>
            <td>{{ $attendance['off'] }}</td>
            <td>{{ $attendance['leave'] }}</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>{{ $attendance['holidays'] }}</td>
            <td>{{ $attendance['total'] }}</td>
            <td>-</td>
        </tr>
    </table>

    <table style="border: 2px solid #000;">
        <thead>
            <tr class="bg-grey fw-bold text-center">
                <td width="24%">DESCRIPTION</td>
                <td width="16%">MONTHLY STRUCTURE</td>
                <td width="16%">MONTHLY EARNINGS</td>
                <td width="22%">ADDITIONS</td>
                <td width="22%">DEDUCTIONS</td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Basic Salary</td>
                <td class="text-end">{{ number_format($fixedMonthlyCTC * 0.5, 2) }}</td>
                <td class="text-end">{{ number_format($basicMonth, 2) }}</td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>HRA</td>
                <td class="text-end">{{ number_format($fixedMonthlyCTC * 0.25, 2) }}</td>
                <td class="text-end">{{ number_format($hraMonth, 2) }}</td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>MEDICAL AL</td>
                <td class="text-end">{{ number_format(2500, 2) }}</td>
                <td class="text-end">{{ number_format($medicalMonth, 2) }}</td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>EDU ALL</td>
                <td class="text-end">{{ number_format(200, 2) }}</td>
                <td class="text-end">{{ number_format($eduMonth, 2) }}</td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>SPL. ALL</td>
                <td class="text-end">{{ number_format($fixedMonthlyCTC - ($fixedMonthlyCTC * 0.75 + 5200), 2) }}</td>
                <td class="text-end">{{ number_format($specialAllowance, 2) }}</td>
                <td></td>
                <td class="text-end">PRO. TAX: {{ number_format($profTax, 2) }}</td>
            </tr>
            <tr>
                <td>LTA</td>
                <td class="text-end">{{ number_format(2500, 2) }}</td>
                <td class="text-end">{{ number_format($ltaMonth, 2) }}</td>
                <td></td>
                <td class="text-end">EPF: {{ number_format($pfAmount, 2) }}</td>
            </tr>
            @for($i=0; $i<8; $i++)
            <tr>
                <td>&nbsp;</td><td></td><td></td><td></td><td></td>
            </tr>
            @endfor
            <tr class="fw-bold">
                <td colspan="3" class="text-end">Total Earnings</td>
                <td class="text-end">{{ number_format($netEarned, 2) }}</td>
                <td class="text-end">Deductions: {{ number_format($profTax + $pfAmount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 10px; border: 2px solid #000; padding: 10px; background-color: #000; color: #fff;">
        <div style="float: left; font-size: 12pt; font-weight: bold;">Net Amount Payable</div>
        <div style="float: right; font-size: 14pt; font-weight: bold;">{{ number_format($netSalary, 2) }}</div>
        <div style="clear: both;"></div>
    </div>

    <div class="footer clearfix">
        <div style="float: left; width: 60%; font-size: 7pt; color: #666;">
            This is a computer generated document and does not require a physical signature.<br>
            Please report discrepancies to the HR Department within 3 days.
        </div>
        <div class="signature small fw-bold">
            Signature of Employee
        </div>
    </div>
</body>
</html>

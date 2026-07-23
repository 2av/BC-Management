using MediatR;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MitraNiidhi.Application.Reports;

namespace MitraNiidhi.Api.Controllers;

[ApiController]
[Route("api/reports")]
[Authorize(Roles = "ClientAdmin,SuperAdmin")]
public class ReportsController(IMediator mediator) : ControllerBase
{
    [HttpGet("overview")]
    public async Task<IActionResult> Overview(CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetReportOverviewQuery(), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpGet("payments")]
    public async Task<IActionResult> Payments(
        [FromQuery] int? groupId,
        [FromQuery] DateOnly? from,
        [FromQuery] DateOnly? to,
        CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetPaymentsReportQuery(groupId, from, to), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpGet("bids")]
    public async Task<IActionResult> Bids([FromQuery] int? groupId, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetBidsReportQuery(groupId), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpGet("export/{type}")]
    public async Task<IActionResult> Export(
        string type,
        [FromQuery] int? groupId,
        [FromQuery] DateOnly? from,
        [FromQuery] DateOnly? to,
        CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new ExportCsvQuery(type, groupId, from, to), cancellationToken);
        if (!result.Succeeded)
            return BadRequest(new { message = result.Error });

        var (fileName, csv) = result.Data!;
        return File(System.Text.Encoding.UTF8.GetBytes(csv), "text/csv", fileName);
    }
}

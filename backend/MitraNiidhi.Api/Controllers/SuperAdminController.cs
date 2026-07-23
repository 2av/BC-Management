using MediatR;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MitraNiidhi.Application.Platform;

namespace MitraNiidhi.Api.Controllers;

[ApiController]
[Route("api/super-admin")]
[Authorize(Roles = "SuperAdmin")]
public class SuperAdminController(IMediator mediator) : ControllerBase
{
    [HttpGet("dashboard")]
    public async Task<IActionResult> Dashboard(CancellationToken ct)
    {
        var result = await mediator.Send(new GetSuperAdminDashboardQuery(), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpGet("clients")]
    public async Task<IActionResult> Clients(CancellationToken ct)
    {
        var result = await mediator.Send(new GetClientsQuery(), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpGet("clients/{id:int}")]
    public async Task<IActionResult> ClientDetail(int id, CancellationToken ct)
    {
        var result = await mediator.Send(new GetClientDetailQuery(id), ct);
        return result.Succeeded ? Ok(result.Data) : NotFound(new { message = result.Error });
    }

    [HttpPost("clients")]
    public async Task<IActionResult> CreateClient([FromBody] CreateClientRequest request, CancellationToken ct)
    {
        var result = await mediator.Send(new CreateClientCommand(request), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPut("clients/{id:int}")]
    public async Task<IActionResult> UpdateClient(int id, [FromBody] UpdateClientRequest request, CancellationToken ct)
    {
        var result = await mediator.Send(new UpdateClientCommand(id, request), ct);
        return result.Succeeded ? Ok(new { message = "Client updated." }) : BadRequest(new { message = result.Error });
    }

    [HttpPatch("clients/{id:int}/status")]
    public async Task<IActionResult> ToggleClientStatus(int id, CancellationToken ct)
    {
        var result = await mediator.Send(new ToggleClientStatusCommand(id), ct);
        return result.Succeeded ? Ok(new { status = result.Data }) : BadRequest(new { message = result.Error });
    }

    [HttpGet("plans")]
    public async Task<IActionResult> Plans(CancellationToken ct)
    {
        var result = await mediator.Send(new GetPlansQuery(), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPost("plans")]
    public async Task<IActionResult> CreatePlan([FromBody] SavePlanRequest request, CancellationToken ct)
    {
        var result = await mediator.Send(new SavePlanCommand(null, request), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPut("plans/{id:int}")]
    public async Task<IActionResult> UpdatePlan(int id, [FromBody] SavePlanRequest request, CancellationToken ct)
    {
        var result = await mediator.Send(new SavePlanCommand(id, request), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpDelete("plans/{id:int}")]
    public async Task<IActionResult> DeletePlan(int id, CancellationToken ct)
    {
        var result = await mediator.Send(new DeletePlanCommand(id), ct);
        return result.Succeeded ? Ok(new { message = "Plan deactivated." }) : BadRequest(new { message = result.Error });
    }

    [HttpGet("subscriptions")]
    public async Task<IActionResult> Subscriptions([FromQuery] int? clientId, CancellationToken ct)
    {
        var result = await mediator.Send(new GetSubscriptionsQuery(clientId), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPost("subscriptions")]
    public async Task<IActionResult> AssignSubscription([FromBody] AssignSubscriptionRequest request, CancellationToken ct)
    {
        var result = await mediator.Send(new AssignSubscriptionCommand(request), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPost("subscriptions/{id:int}/extend")]
    public async Task<IActionResult> ExtendSubscription(int id, [FromBody] ExtendSubscriptionRequest request, CancellationToken ct)
    {
        var result = await mediator.Send(new ExtendSubscriptionCommand(id, request), ct);
        return result.Succeeded ? Ok(new { message = "Subscription extended." }) : BadRequest(new { message = result.Error });
    }

    [HttpPost("subscriptions/{id:int}/cancel")]
    public async Task<IActionResult> CancelSubscription(int id, CancellationToken ct)
    {
        var result = await mediator.Send(new CancelSubscriptionCommand(id), ct);
        return result.Succeeded ? Ok(new { message = "Subscription cancelled." }) : BadRequest(new { message = result.Error });
    }

    [HttpGet("payments")]
    public async Task<IActionResult> Payments([FromQuery] string? status, [FromQuery] int? clientId, CancellationToken ct)
    {
        var result = await mediator.Send(new GetSubscriptionPaymentsQuery(status, clientId), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPatch("payments/{id:int}/complete")]
    public async Task<IActionResult> MarkPaymentComplete(int id, CancellationToken ct)
    {
        var result = await mediator.Send(new MarkSubscriptionPaymentCommand(id), ct);
        return result.Succeeded ? Ok(new { message = "Payment marked complete." }) : BadRequest(new { message = result.Error });
    }

    [HttpGet("audit-logs")]
    public async Task<IActionResult> AuditLogs([FromQuery] int page = 1, [FromQuery] int pageSize = 50, CancellationToken ct = default)
    {
        var result = await mediator.Send(new GetAuditLogsQuery(page, pageSize), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }
}

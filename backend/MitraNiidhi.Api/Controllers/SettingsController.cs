using MediatR;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MitraNiidhi.Application.Settings;

namespace MitraNiidhi.Api.Controllers;

[ApiController]
[Route("api/settings")]
[Authorize]
public class SettingsController(IMediator mediator) : ControllerBase
{
    [HttpGet("payment-config")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> GetPaymentConfig(CancellationToken ct)
    {
        var result = await mediator.Send(new GetPaymentConfigQuery(), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPut("payment-config")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> UpdatePaymentConfig([FromBody] UpdatePaymentConfigRequest request, CancellationToken ct)
    {
        var result = await mediator.Send(new UpdatePaymentConfigCommand(request), ct);
        return result.Succeeded ? Ok(new { message = "Payment config saved." }) : BadRequest(new { message = result.Error });
    }

    [HttpGet]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> GetSystemSettings(CancellationToken ct)
    {
        var result = await mediator.Send(new GetSystemSettingsQuery(), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPut]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> UpdateSystemSettings([FromBody] UpdateSystemSettingsRequest request, CancellationToken ct)
    {
        var result = await mediator.Send(new UpdateSystemSettingsCommand(request), ct);
        return result.Succeeded ? Ok(new { message = "Settings saved." }) : BadRequest(new { message = result.Error });
    }

    [HttpGet("schema")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> CheckSchema(CancellationToken ct)
    {
        var result = await mediator.Send(new CheckSchemaQuery(), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPost("schema/migrate")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> MigrateSchema(CancellationToken ct)
    {
        var result = await mediator.Send(new MigrateSchemaCommand(), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }
}

[ApiController]
[Authorize]
public class PaymentQrController(IMediator mediator) : ControllerBase
{
    [HttpGet("api/groups/{groupId:int}/payments/qr")]
    [Authorize(Roles = "Member,ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> GetQr(int groupId, [FromQuery] int month, CancellationToken ct)
    {
        var result = await mediator.Send(new GetPaymentQrQuery(groupId, month), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }
}

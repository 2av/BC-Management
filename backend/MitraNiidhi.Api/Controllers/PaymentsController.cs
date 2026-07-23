using MediatR;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MitraNiidhi.Application.Payments;

namespace MitraNiidhi.Api.Controllers;

[ApiController]
[Authorize]
public class PaymentsController(IMediator mediator) : ControllerBase
{
    [HttpGet("api/groups/{groupId:int}/payments")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> GroupPayments(
        int groupId,
        [FromQuery] int? month,
        [FromQuery] string? status,
        CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetGroupPaymentsQuery(groupId, month, status), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : NotFound(new { message = result.Error });
    }

    [HttpPatch("api/payments/{id:int}")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> Update(int id, [FromBody] UpdatePaymentRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new UpdatePaymentCommand(id, request), cancellationToken);
        return result.Succeeded ? Ok(new { message = "Payment updated." }) : BadRequest(new { message = result.Error });
    }

    [HttpPost("api/groups/{groupId:int}/payments/bulk-mark-paid")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> BulkMarkPaid(
        int groupId,
        [FromBody] BulkMarkPaidRequest request,
        CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new BulkMarkPaidCommand(groupId, request), cancellationToken);
        return result.Succeeded
            ? Ok(new { message = $"Marked {result.Data} payment(s) as paid.", count = result.Data })
            : BadRequest(new { message = result.Error });
    }

    [HttpGet("api/members/me/payments")]
    [Authorize(Roles = "Member")]
    public async Task<IActionResult> MyPayments(CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetMyPaymentsQuery(), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }
}

using MediatR;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MitraNiidhi.Application.Invoices;

namespace MitraNiidhi.Api.Controllers;

[ApiController]
[Route("api/groups/{groupId:int}/members/{memberId:int}/invoice")]
[Authorize(Roles = "Member,ClientAdmin,SuperAdmin")]
public class InvoicesController(IMediator mediator) : ControllerBase
{
    [HttpGet]
    public async Task<IActionResult> Get(int groupId, int memberId, CancellationToken ct)
    {
        var result = await mediator.Send(new GetInvoiceQuery(groupId, memberId), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }
}

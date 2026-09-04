using System;

namespace Harapeco.AppUpdateLanding
{
    public sealed class AppUpdateLandingException : Exception
    {
        public AppUpdateLandingException(string code, string message, long httpStatus = 0)
            : base(message)
        {
            Code = string.IsNullOrWhiteSpace(code) ? "unknown_error" : code;
            HttpStatus = httpStatus;
        }

        public AppUpdateLandingException(
            string code,
            string message,
            Exception innerException,
            long httpStatus = 0)
            : base(message, innerException)
        {
            Code = string.IsNullOrWhiteSpace(code) ? "unknown_error" : code;
            HttpStatus = httpStatus;
        }

        public string Code { get; }

        public long HttpStatus { get; }
    }
}
